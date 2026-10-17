<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformLedgerEntry;
use App\Models\PlatformSetting;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Record all ledger entries when an order transitions to COMPLETED.
     *
     * Business rules:
     * - Store receives money: PAYMENT_RECEIPT (digital) or COD_DEPOSIT (COD) [Credit]
     * - Store pays courier: COURIER_WAGE [Debit]
     * - Store pays platform: PLATFORM_COMMISSION (Rp1.000 flat from platform_settings) [Debit]
     * - Platform records revenue: PLATFORM_COMMISSION [Accrued]
     */
    public function recordOrderCompletion(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // 1. Check idempotency: if already recorded for this order, skip duplicate creation
            $existingStoreEntries = StoreLedgerEntry::where('order_id', $order->id)->exists();
            if ($existingStoreEntries) {
                return;
            }

            $finalAmount = (int) ($order->final_amount ?: $order->estimated_amount);
            $courierWage = (int) ($order->courier_wage > 0 ? $order->courier_wage : 5000);
            $platformCommission = PlatformSetting::getPlatformCommission();

            // Determine if payment was COD or Digital
            $latestPayment = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->latest()
                ->first();

            $isCod = ($latestPayment && $latestPayment->method === 'cod') ||
                     ($order->payment_status === 'PAID' && str_contains(strtolower($order->customer_notes ?? ''), 'cod'));

            $receiptEntryType = $isCod ? 'COD_DEPOSIT' : 'PAYMENT_RECEIPT';
            $receiptDescription = $isCod
                ? "Setoran pembayaran COD dari pelanggan untuk pesanan {$order->order_number}."
                : "Penerimaan pembayaran digital pelanggan (Xendit) untuk pesanan {$order->order_number}.";

            // 2. Store Ledger: Income (Credit)
            StoreLedgerEntry::create([
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'entry_type' => $receiptEntryType,
                'direction' => 'credit',
                'amount' => $finalAmount,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'description' => $receiptDescription,
                'created_by' => $order->courier_id ?: $order->customer_id,
            ]);

            // 3. Store Ledger: Courier Wage Expense (Debit)
            StoreLedgerEntry::create([
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'entry_type' => 'COURIER_WAGE',
                'direction' => 'debit',
                'amount' => $courierWage,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'description' => "Pembayaran upah pengantaran kurir untuk pesanan {$order->order_number}.",
                'created_by' => $order->store?->owner_user_id,
            ]);

            // 4. Store Ledger: Platform Commission Expense (Debit)
            StoreLedgerEntry::create([
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'entry_type' => 'PLATFORM_COMMISSION',
                'direction' => 'debit',
                'amount' => $platformCommission,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'description' => "Biaya komisi platform LaundryKu flat untuk pesanan {$order->order_number}.",
                'created_by' => null,
            ]);

            // 5. Platform Ledger: Platform Commission Revenue (Accrued)
            PlatformLedgerEntry::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'entry_type' => 'PLATFORM_COMMISSION',
                ],
                [
                    'store_id' => $order->store_id,
                    'amount' => $platformCommission,
                    'status' => 'accrued',
                ]
            );
        });
    }

    /**
     * Get financial summary and ledger statistics for a store.
     */
    public function getStoreSummary(Store $store): array
    {
        $entries = StoreLedgerEntry::where('store_id', $store->id);

        $totalCredit = (int) StoreLedgerEntry::where('store_id', $store->id)
            ->where('direction', 'credit')
            ->sum('amount');

        $totalDebit = (int) StoreLedgerEntry::where('store_id', $store->id)
            ->where('direction', 'debit')
            ->sum('amount');

        $totalCod = (int) StoreLedgerEntry::where('store_id', $store->id)
            ->where('entry_type', 'COD_DEPOSIT')
            ->sum('amount');

        $totalDigital = (int) StoreLedgerEntry::where('store_id', $store->id)
            ->where('entry_type', 'PAYMENT_RECEIPT')
            ->sum('amount');

        $totalWages = (int) StoreLedgerEntry::where('store_id', $store->id)
            ->where('entry_type', 'COURIER_WAGE')
            ->sum('amount');

        $totalCommission = (int) StoreLedgerEntry::where('store_id', $store->id)
            ->where('entry_type', 'PLATFORM_COMMISSION')
            ->sum('amount');

        $netBalance = $totalCredit - $totalDebit;

        return [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'total_income' => $totalCredit,
            'total_expense' => $totalDebit,
            'net_balance' => $netBalance,
            'breakdown' => [
                'digital_payments' => $totalDigital,
                'cod_deposits' => $totalCod,
                'courier_wages' => $totalWages,
                'platform_commission' => $totalCommission,
            ],
            'total_transactions' => StoreLedgerEntry::where('store_id', $store->id)->count(),
        ];
    }
}
