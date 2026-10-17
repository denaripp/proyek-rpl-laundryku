<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_configurations')->insertOrIgnore([
            [
                'key' => 'COMPENSATION_POLICY',
                'value' => json_encode([
                    'placeholder' => '[COMPENSATION_POLICY]',
                    'description' => 'Kebijakan kompensasi/refund akibat dispute. HARUS diputuskan manusia (Platform Admin/Owner). Tidak boleh otomatis, tidak boleh diputuskan AI Assistant.',
                    'requires_human_approval' => true,
                    'auto_compensation' => false,
                    'authorized_roles' => ['platform_admin', 'platform_owner'],
                ]),
                'description' => 'Compensation policy for disputes (human-decided, never automatic).',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'DISPUTE_SLA',
                'value' => json_encode([
                    'placeholder' => '[DISPUTE_SLA]',
                    'first_response_hours' => 24,
                    'resolution_days' => 7,
                    'description' => 'SLA penanganan dispute. Placeholder config, bukan hardcode.',
                ]),
                'description' => 'Service Level Agreement for dispute handling.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_configurations')
            ->whereIn('key', ['COMPENSATION_POLICY', 'DISPUTE_SLA'])
            ->delete();
    }
};