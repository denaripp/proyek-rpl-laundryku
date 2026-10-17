<?php

namespace App\Console\Commands;

use App\Services\Payment\XenditService;
use Illuminate\Console\Command;

class ExpirePendingPaymentsCommand extends Command
{
    protected $signature = 'payments:expire';
    protected $description = 'Expire pending payments that have passed their expiration time';

    public function handle(XenditService $xenditService): int
    {
        $this->info('Checking for expired pending payments...');

        $expiredCount = 0;

        // Get all pending payments that have expired
        $expired = \App\Models\Payment::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $payment) {
            $payment->status = 'expired';
            $payment->save();

            // Log the expiration event
            \App\Models\PaymentEvent::create([
                'payment_id' => $payment->id,
                'event_type' => 'auto_expired',
                'payload' => [
                    'expired_at' => now()->toISOString(),
                    'original_expires_at' => $payment->expires_at->toISOString(),
                ],
                'occurred_at' => now(),
            ]);

            $expiredCount++;
            $this->line("  - Payment #{$payment->id} expired");
        }

        $this->info("Completed: {$expiredCount} payment(s) expired.");

        return 0;
    }
}