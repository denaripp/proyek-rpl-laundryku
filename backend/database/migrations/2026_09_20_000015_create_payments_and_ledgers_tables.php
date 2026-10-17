<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Platform Settings table (PRD 11.8)
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->jsonb('value_json');
            $table->text('description')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seed initial platform commission configuration
        DB::table('platform_settings')->insert([
            'key' => 'platform_commission_flat',
            'value_json' => json_encode(['amount' => 1000]),
            'description' => 'Besaran komisi platform flat per order selesai (Rupiah)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Payments table (PRD 11.6)
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('provider', 50)->default('xendit'); // xendit, cod
            $table->string('method', 50); // va, qris, cod, ewallet
            $table->string('provider_reference')->nullable();
            $table->bigInteger('amount');
            $table->string('status', 30)->default('pending'); // pending, paid, failed, expired
            $table->string('idempotency_key')->unique();
            $table->jsonb('payment_details')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['provider', 'provider_reference']);
        });

        // 3. Payment Webhook Events table (PRD 11.6)
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 50)->default('xendit');
            $table->string('provider_event_id')->unique();
            $table->string('idempotency_key')->nullable()->index();
            $table->string('payload_hash');
            $table->string('processing_status', 30)->default('received'); // received, processed, failed, ignored
            $table->jsonb('raw_payload')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'processing_status']);
        });

        // 4. Store Ledger Entries table (PRD 11.6)
        Schema::create('store_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('entry_type', 50); // COD_RECEIVABLE, COD_DEPOSIT, COURIER_WAGE, PLATFORM_COMMISSION, PAYMENT_RECEIPT, ADJUSTMENT
            $table->string('direction', 10); // debit, credit
            $table->bigInteger('amount');
            $table->string('reference_type', 50)->nullable(); // order, payment, withdrawal, manual
            $table->uuid('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
            $table->index(['order_id']);
            $table->index(['entry_type', 'created_at']);
        });

        // 5. Platform Ledger Entries table (PRD 11.6)
        Schema::create('platform_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('entry_type', 50)->default('PLATFORM_COMMISSION');
            $table->bigInteger('amount');
            $table->string('status', 30)->default('accrued'); // accrued, settled, adjusted
            $table->timestamps();

            $table->unique(['order_id', 'entry_type']);
            $table->index(['store_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_ledger_entries');
        Schema::dropIfExists('store_ledger_entries');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('platform_settings');
    }
};
