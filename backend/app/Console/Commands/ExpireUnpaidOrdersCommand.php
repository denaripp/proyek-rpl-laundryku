<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderStateMachine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireUnpaidOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:expire-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otomatis mengubah status pesanan yang melewati batas waktu pembayaran menjadi PAYMENT_EXPIRED.';

    /**
     * Execute the console command.
     */
    public function handle(OrderStateMachine $stateMachine): int
    {
        $this->info('Mengecek pesanan yang melewati batas waktu pembayaran...');

        $awaitingOrders = Order::with(['payments', 'customer'])
            ->where('status', 'AWAITING_PAYMENT')
            ->where('payment_status', '!=', 'PAID')
            ->get();

        $expiredCount = 0;
        $now = now();

        foreach ($awaitingOrders as $order) {
            $expiresAt = $this->resolveExpirationTime($order);

            if ($now->greaterThanOrEqualTo($expiresAt)) {
                try {
                    $didExpire = DB::transaction(function () use ($order, $stateMachine) {
                        // Re-query with lockForUpdate to prevent race condition with webhook/simultaneous payment
                        $freshOrder = Order::where('id', $order->id)->lockForUpdate()->first();

                        if (!$freshOrder || $freshOrder->status !== 'AWAITING_PAYMENT' || $freshOrder->payment_status === 'PAID') {
                            return false;
                        }

                        $systemActor = $freshOrder->customer ?: User::first();

                        $stateMachine->transition(
                            $freshOrder,
                            'PAYMENT_EXPIRED',
                            $systemActor,
                            ['is_system' => true],
                            'Batas waktu pembayaran telah habis. Pesanan otomatis dibatalkan oleh sistem.'
                        );

                        return true;
                    });

                    if ($didExpire) {
                        $expiredCount++;
                        $this->line("Pesanan #{$order->order_number} telah diubah menjadi PAYMENT_EXPIRED.");
                        Log::info("Order #{$order->order_number} expired automatically by scheduler.");
                    }
                } catch (\Throwable $e) {
                    $this->error("Gagal memproses expiry untuk pesanan #{$order->order_number}: {$e->getMessage()}");
                    Log::error("Failed to auto-expire order #{$order->order_number}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Selesai. Total pesanan kedaluwarsa: {$expiredCount}");

        return Command::SUCCESS;
    }

    /**
     * Resolve the expiration timestamp for an order awaiting payment.
     */
    protected function resolveExpirationTime(Order $order): Carbon
    {
        // 1. Check latest payment details for explicit expiration (e.g. from VA or QRIS)
        $latestPayment = $order->payments->first();
        if ($latestPayment && is_array($latestPayment->payment_details)) {
            $rawExpiry = $latestPayment->payment_details['expiration_date'] ?? $latestPayment->payment_details['expires_at'] ?? null;
            if ($rawExpiry) {
                try {
                    return Carbon::parse($rawExpiry);
                } catch (\Throwable $e) {
                    // fallback below
                }
            }
        }

        // 2. Fallback to configured minutes after price approval, update time, or creation
        $baseTime = $order->price_approved_at ?: $order->updated_at ?: $order->created_at;
        $fallbackMinutes = (int) config('payment.expiry_minutes', 1440);

        return $baseTime ? (clone $baseTime)->addMinutes($fallbackMinutes) : now()->subMinute();
    }
}
