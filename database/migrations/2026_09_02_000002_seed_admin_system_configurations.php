<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 21 — Seed remaining admin-editable system configurations
 * (placeholder PRD items that must NOT be hardcoded).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            [
                'key' => 'COD_MAX_AMOUNT',
                'value' => json_encode([
                    'placeholder' => '[COD_MAX_AMOUNT]',
                    'value' => 500000,
                    'currency' => 'IDR',
                    'description' => 'Maximum order value allowed for COD payment method. Orders above this must use online payment.',
                ]),
                'description' => 'COD maximum order amount cap.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'COD_VERIFICATION_METHOD',
                'value' => json_encode([
                    'placeholder' => '[COD_VERIFICATION_METHOD]',
                    'options' => ['photo', 'signature', 'video', 'none'],
                    'default' => 'photo',
                    'description' => 'How courier verifies COD payment receipt from customer at pickup.',
                ]),
                'description' => 'COD verification method at pickup.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'COD_REFUND_METHOD',
                'value' => json_encode([
                    'placeholder' => '[COD_REFUND_METHOD]',
                    'options' => ['bank_transfer', 'e_wallet', 'cash', 'store_credit'],
                    'default' => 'bank_transfer',
                    'description' => 'Default refund channel when COD order is cancelled/disputed after pickup.',
                ]),
                'description' => 'COD refund channel.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'WEIGHT_VARIANCE_THRESHOLD',
                'value' => json_encode([
                    'placeholder' => '[WEIGHT_VARIANCE_THRESHOLD]',
                    'value_percent' => 10,
                    'description' => 'Allowed variance between estimated and actual weight. If exceeded, customer must confirm new price.',
                ]),
                'description' => 'Weight variance threshold requiring customer re-confirmation.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'PRICE_CHANGE_CONFIRMATION_THRESHOLD',
                'value' => json_encode([
                    'placeholder' => '[PRICE_CHANGE_CONFIRMATION_THRESHOLD]',
                    'value_percent' => 5,
                    'description' => 'If actual price differs from estimate by more than this %, require customer to re-confirm before payment.',
                ]),
                'description' => 'Price change confirmation threshold.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'COURIER_ASSIGNMENT_TIMEOUT',
                'value' => json_encode([
                    'placeholder' => '[COURIER_ASSIGNMENT_TIMEOUT]',
                    'value_minutes' => 30,
                    'description' => 'How long to wait for a courier to accept before auto-failing the order.',
                ]),
                'description' => 'Courier assignment timeout in minutes.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'MAX_PICKUP_REATTEMPT',
                'value' => json_encode([
                    'placeholder' => '[MAX_PICKUP_REATTEMPT]',
                    'value' => 3,
                    'description' => 'Maximum pickup re-attempts before order auto-cancels.',
                ]),
                'description' => 'Maximum pickup re-attempts.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'MAX_DELIVERY_REATTEMPT',
                'value' => json_encode([
                    'placeholder' => '[MAX_DELIVERY_REATTEMPT]',
                    'value' => 3,
                    'description' => 'Maximum delivery re-attempts before order auto-cancels.',
                ]),
                'description' => 'Maximum delivery re-attempts.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'GPS_RETENTION_PERIOD',
                'value' => json_encode([
                    'placeholder' => '[GPS_RETENTION_PERIOD]',
                    'value_days' => 30,
                    'description' => 'How long GPS coordinates from courier tasks are retained before pruning.',
                ]),
                'description' => 'GPS retention period in days.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'AI_DAILY_LIMIT',
                'value' => json_encode([
                    'placeholder' => '[AI_DAILY_LIMIT]',
                    'value' => 200,
                    'description' => 'Maximum AI chat messages per user per day. Anti-abuse.',
                ]),
                'description' => 'AI chat daily limit per user.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'AI_RATE_LIMIT',
                'value' => json_encode([
                    'placeholder' => '[AI_RATE_LIMIT]',
                    'value' => 30,
                    'window_minutes' => 60,
                    'description' => 'Maximum AI chat messages per user per hour.',
                ]),
                'description' => 'AI chat hourly rate limit per user.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'BRAND_NAME',
                'value' => json_encode([
                    'placeholder' => '[BRAND_NAME]',
                    'value' => 'LANDURYKU',
                    'description' => 'Application/brand display name.',
                ]),
                'description' => 'Brand name shown in UI and emails.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'BRAND_TAGLINE',
                'value' => json_encode([
                    'placeholder' => '[BRAND_TAGLINE]',
                    'value' => 'Laundry marketplace untuk semua',
                    'description' => 'Brand tagline shown on landing page.',
                ]),
                'description' => 'Brand tagline.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'BRAND_CONTACT_EMAIL',
                'value' => json_encode([
                    'placeholder' => '[BRAND_CONTACT_EMAIL]',
                    'value' => 'hello@landuryku.id',
                    'description' => 'Public-facing brand contact email.',
                ]),
                'description' => 'Brand public contact email.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'BRAND_CONTACT_PHONE',
                'value' => json_encode([
                    'placeholder' => '[BRAND_CONTACT_PHONE]',
                    'value' => '+62-21-0000-0000',
                    'description' => 'Public-facing brand contact phone.',
                ]),
                'description' => 'Brand public contact phone.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('system_configurations')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        DB::table('system_configurations')
            ->whereIn('key', [
                'COD_MAX_AMOUNT', 'COD_VERIFICATION_METHOD', 'COD_REFUND_METHOD',
                'WEIGHT_VARIANCE_THRESHOLD', 'PRICE_CHANGE_CONFIRMATION_THRESHOLD',
                'COURIER_ASSIGNMENT_TIMEOUT', 'MAX_PICKUP_REATTEMPT',
                'MAX_DELIVERY_REATTEMPT', 'GPS_RETENTION_PERIOD',
                'AI_DAILY_LIMIT', 'AI_RATE_LIMIT',
                'BRAND_NAME', 'BRAND_TAGLINE', 'BRAND_CONTACT_EMAIL', 'BRAND_CONTACT_PHONE',
            ])
            ->delete();
    }
};