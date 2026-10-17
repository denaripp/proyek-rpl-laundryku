<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_configurations')->insertOrIgnore([
            [
                'key' => 'CS_NAME',
                'value' => json_encode([
                    'placeholder' => '[CS_NAME]',
                    'value' => 'Tim Customer Service LANDURYKU',
                    'description' => 'Nama/nama tim CS untuk human escalation. Editable via admin setting.',
                ]),
                'description' => 'Customer service name for human escalation.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'CS_EMAIL',
                'value' => json_encode([
                    'placeholder' => '[CS_EMAIL]',
                    'value' => 'cs@landuryku.id',
                    'description' => 'Email CS untuk human escalation.',
                ]),
                'description' => 'Customer service email for human escalation.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'CS_WHATSAPP',
                'value' => json_encode([
                    'placeholder' => '[CS_WHATSAPP]',
                    'value' => '+62-812-0000-0000',
                    'description' => 'Nomor WhatsApp CS untuk human escalation.',
                ]),
                'description' => 'Customer service WhatsApp for human escalation.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'AI_RATE_LIMIT_PER_HOUR',
                'value' => json_encode([
                    'value' => 30,
                    'description' => 'Maksimal pesan AI per user per jam.',
                ]),
                'description' => 'Per-user AI chat rate limit (messages per hour).',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'AI_ABUSE_THRESHOLD',
                'value' => json_encode([
                    'value' => 3,
                    'description' => 'Jumlah pesan mencurigakan (pola injection) per jam sebelum user diblokir sementara dari AI chat.',
                ]),
                'description' => 'Abuse detection threshold for AI chat.',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_configurations')
            ->whereIn('key', ['CS_NAME', 'CS_EMAIL', 'CS_WHATSAPP', 'AI_RATE_LIMIT_PER_HOUR', 'AI_ABUSE_THRESHOLD'])
            ->delete();
    }
};