<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->unique()->nullable()->after('xendit_payment_channel');
            }
            if (!Schema::hasColumn('payments', 'webhook_received_at')) {
                $table->timestamp('webhook_received_at')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('payments', 'xendit_webhook_id')) {
                $table->string('xendit_webhook_id', 64)->nullable()->after('xendit_invoice_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'webhook_received_at', 'xendit_webhook_id']);
        });
    }
};