<?php

namespace App\Services\Settlement;

use App\Models\AuditLog;
use App\Models\CommissionSetting;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\SettlementItem;
use App\Models\SettlementPeriod;
use App\Models\Store;
use App\Models\SystemConfiguration;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Settlement Service — internal record-keeping & calculation only (MVP).
 *
 * No automatic money transfer. Generates an auditable settlement report
 * per store per period that finance verifies manually.
 *
 * Rules:
 * - Only COMPLETED orders whose dispute window has passed are eligible.
 * - Orders with an OPEN dispute are held until the dispute is closed.
 * - Orders already settled are never double-counted (unique per order).
 * - Commission/fee rates come from config placeholders + per-store overrides,
 *   never hardcoded numbers.
 */
class SettlementService
{
    /**
     * Generate settlement for one store over a date range.
     * Eligibility window: order completed_at <= now - dispute_window_days.
     */
    public function generateSettlement(Store $store, User $actor, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): SettlementPeriod
    {
        $config = $this->getSettlementConfig();
        $interval = $config['interval'] ?? 'weekly';
        $disputeWindowDays = (int) ($config['dispute_window_days'] ?? 7);

        $cutoff = now()->subDays($disputeWindowDays);

        if ($to !== null && $to->gt($cutoff)) {
            // Never settle orders still inside the dispute window
            $to = $cutoff;
        }

        if ($from === null) {
            $last = SettlementPeriod::where('store_id', $store->id)
                ->orderByDesc('period_end')
                ->first();
            $from = $last ? $last->period_end : now()->subDays(30);
        }

        if ($to === null) {
            $to = $cutoff;
        }

        if ($from->gte($to)) {
            throw new Exception('Settlement window is empty — nothing to settle.');
        }

        return DB::transaction(function () use ($store, $actor, $from, $to, $config, $interval, $disputeWindowDays) {
            // Lock store row to serialize concurrent settlement generation for this store
            Store::where('id', $store->id)->lockForUpdate()->firstOrFail();

            // Idempotency: no overlapping period for the same store
            $overlap = SettlementPeriod::where('store_id', $store->id)
                ->where('period_start', '<', $to)
                ->where('period_end', '>', $from)
                ->lockForUpdate()
                ->exists();
            if ($overlap) {
                throw new Exception('An overlapping settlement period already exists for this store.');
            }

            $orders = $this->eligibleOrders($store, $from, $to, $disputeWindowDays);

            $period = SettlementPeriod::create([
                'reference' => $this->generateReference($store, $from, $to),
                'store_id' => $store->id,
                'period_start' => $from,
                'period_end' => $to,
                'interval' => $interval,
                'gross_amount' => 0,
                'commission_amount' => 0,
                'platform_fee_amount' => 0,
                'net_amount' => 0,
                'order_count' => 0,
                'status' => 'generated',
                'config_snapshot' => $config,
            ]);

            $gross = '0';
            $commissionTotal = '0';
            $feeTotal = '0';
            $count = 0;

            foreach ($orders as $order) {
                $orderTotal = (float) $order->total_amount;
                $commission = $this->calculateCommission($store, $order, $orderTotal);
                $platformFee = $this->calculatePlatformFee($orderTotal);
                $net = $orderTotal - $commission - $platformFee;

                SettlementItem::create([
                    'settlement_period_id' => $period->id,
                    'order_id' => $order->id,
                    'order_total' => $orderTotal,
                    'commission_amount' => $commission,
                    'platform_fee_amount' => $platformFee,
                    'net_amount' => $net,
                ]);

                $gross = bcadd($gross, (string) $orderTotal, 2);
                $commissionTotal = bcadd($commissionTotal, (string) $commission, 2);
                $feeTotal = bcadd($feeTotal, (string) $platformFee, 2);
                $count++;
            }

            $netTotal = bcsub(bcsub($gross, $commissionTotal, 2), $feeTotal, 2);

            $period->update([
                'gross_amount' => $gross,
                'commission_amount' => $commissionTotal,
                'platform_fee_amount' => $feeTotal,
                'net_amount' => $netTotal,
                'order_count' => $count,
            ]);

            $this->logAudit($actor, 'settlement_generated', 'settlement_periods', $period->id, null, [
                'store_id' => $store->id,
                'reference' => $period->reference,
                'period_start' => $from->toDateTimeString(),
                'period_end' => $to->toDateTimeString(),
                'order_count' => $count,
                'gross_amount' => $gross,
                'commission_amount' => $commissionTotal,
                'platform_fee_amount' => $feeTotal,
                'net_amount' => $netTotal,
            ]);

            return $period->fresh();
        });
    }

    /**
     * Finance/admin verifies a generated settlement (manual step).
     */
    public function verifySettlement(SettlementPeriod $period, User $verifier): SettlementPeriod
    {
        if ($period->status !== 'generated') {
            throw new Exception('Only GENERATED settlements can be verified.');
        }

        $period->status = 'verified';
        $period->verified_by = $verifier->id;
        $period->verified_at = now();
        $period->save();

        $this->logAudit($verifier, 'settlement_verified', 'settlement_periods', $period->id,
            ['status' => 'generated'],
            ['status' => 'verified', 'reference' => $period->reference]
        );

        return $period;
    }

    /**
     * Orders eligible for settlement in the window:
     * - COMPLETED
     * - completed_at within window AND dispute window already passed
     * - no OPEN dispute
     * - not already settled
     */
    public function eligibleOrders(Store $store, \DateTimeInterface $from, \DateTimeInterface $to, ?int $disputeWindowDays = null): array
    {
        $config = $this->getSettlementConfig();
        $disputeWindowDays = $disputeWindowDays ?? (int) ($config['dispute_window_days'] ?? 7);

        return Order::where('store_id', $store->id)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            // dispute window must have fully passed
            ->where('completed_at', '<=', now()->subDays($disputeWindowDays))
            // no open dispute on the order
            ->whereDoesntHave('disputes', function ($q) {
                $q->whereIn('status', Dispute::OPEN_STATUSES);
            })
            // no active, processing, or completed refund on the order
            ->whereDoesntHave('refunds', function ($q) {
                $q->whereIn('status', ['requested', 'processing', 'completed']);
            })
            // never double-settle
            ->whereDoesntHave('settlementItem')
            ->get()
            ->all();
    }

    /**
     * Commission for an order: per-store override first (commission_settings),
     * then store.commission_rate, then MARKETPLACE_COMMISSION default config.
     */
    public function calculateCommission(Store $store, Order $order, float $orderTotal): float
    {
        // 1. Per-store override via commission_settings (active & effective now)
        $setting = CommissionSetting::where('store_id', $store->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', now()->toDateString());
            })
            ->latest('id')
            ->first();

        if ($setting) {
            if ($setting->fee_type === 'fixed') {
                return round((float) ($setting->fixed_amount ?? 0), 2);
            }
            return round($orderTotal * ((float) $setting->commission_rate / 100), 2);
        }

        // 2. Store-level commission_rate column
        if ($store->commission_rate !== null) {
            return round($orderTotal * ((float) $store->commission_rate / 100), 2);
        }

        // 3. Platform default from config (never hardcoded)
        $config = $this->getCommissionConfig();
        $rate = (float) ($config['rate_percent'] ?? 0);

        return round($orderTotal * ($rate / 100), 2);
    }

    /**
     * Platform fee per order from PLATFORM_FEE config.
     */
    public function calculatePlatformFee(float $orderTotal): float
    {
        $config = $this->getPlatformFeeConfig();

        if (($config['fee_type'] ?? 'fixed') === 'percentage') {
            return round($orderTotal * ((float) ($config['rate_percent'] ?? 0) / 100), 2);
        }

        return round((float) ($config['amount'] ?? 0), 2);
    }

    // ========== CONFIG (placeholders, not hardcoded) ==========

    public function getSettlementConfig(): array
    {
        $config = SystemConfiguration::where('key', 'SETTLEMENT_PERIOD')->first();
        $values = $config ? (array) $config->value : [];

        // dispute window comes from DISPUTE_SLA
        $sla = SystemConfiguration::where('key', 'DISPUTE_SLA')->first();
        $slaValues = $sla ? (array) $sla->value : [];
        $values['dispute_window_days'] = $slaValues['resolution_days'] ?? 7;

        return $values;
    }

    public function getCommissionConfig(): array
    {
        $config = SystemConfiguration::where('key', 'MARKETPLACE_COMMISSION')->first();
        return $config ? (array) $config->value : [];
    }

    public function getPlatformFeeConfig(): array
    {
        $config = SystemConfiguration::where('key', 'PLATFORM_FEE')->first();
        return $config ? (array) $config->value : [];
    }

    // ========== INTERNALS ==========

    protected function generateReference(Store $store, \DateTimeInterface $from, \DateTimeInterface $to): string
    {
        return sprintf(
            'STL-%d-%s-%s',
            $store->id,
            $from->format('Ymd'),
            $to->format('Ymd')
        );
    }

    protected function logAudit(User $actor, string $action, string $resourceType, int $resourceId, ?array $oldValue, ?array $newValue): void
    {
        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before_value' => $oldValue,
            'after_value' => $newValue,
            'ip_address' => request()->ip() ?? null,
            'user_agent' => request()->userAgent() ?? null,
            'created_at' => now(),
        ]);
    }
}