<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Redact all OTP codes, plain credentials, and sensitive verification data from notifications table.
     */
    public function up(): void
    {
        // 1. Redact all rows containing OTP event types or messages
        DB::table('notifications')
            ->where('event_type', 'like', '%OTP%')
            ->orWhere('title', 'like', '%OTP%')
            ->orWhere('message', 'like', '%OTP%')
            ->update([
                'title' => 'Kode Verifikasi Dikirim',
                'message' => 'Kode verifikasi telah dikirim ke kanal registrasi Anda.',
                'payload' => json_encode(['destination' => '[REDACTED]']),
            ]);

        // 2. Redact any remaining notification message that might contain 6-digit numeric OTP codes via PHP preg_replace
        $allNotifications = DB::table('notifications')->get();

        foreach ($allNotifications as $row) {
            if ($row->message && preg_match('/[0-9]{6}/', $row->message)) {
                $cleanedMessage = preg_replace('/[0-9]{6}/', '[REDACTED]', $row->message);
                DB::table('notifications')
                    ->where('id', $row->id)
                    ->update([
                        'message' => $cleanedMessage,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Security redactions are irreversible
    }
};
