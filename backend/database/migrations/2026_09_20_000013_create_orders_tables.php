<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Orders table (PRD 11.5)
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->foreignUuid('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignUuid('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('pickup_address_snapshot');
            $table->jsonb('delivery_address_snapshot');
            $table->string('status')->default('PENDING_PICKUP')->index();
            $table->string('payment_status')->default('UNPAID')->index(); // UNPAID, PAID, PAYMENT_FAILED, PAYMENT_EXPIRED
            $table->decimal('estimated_weight_kg', 8, 3)->nullable();
            $table->decimal('actual_weight_kg', 8, 3)->nullable();
            $table->bigInteger('estimated_amount');
            $table->bigInteger('final_amount')->nullable();
            $table->bigInteger('delivery_fee')->default(0);
            $table->bigInteger('commission_amount')->default(1000); // Rp1.000 flat platform fee
            $table->bigInteger('courier_wage')->default(0);
            $table->text('customer_notes')->nullable();
            $table->timestamp('price_approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['store_id', 'status']);
            $table->index(['courier_id', 'status']);
        });

        // 2. Order items with price snapshot (PRD 11.5)
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignUuid('service_id')->constrained('store_services')->onDelete('cascade');
            $table->string('service_name_snapshot');
            $table->string('pricing_unit_snapshot', 20)->default('kg');
            $table->bigInteger('unit_price_snapshot');
            $table->bigInteger('minimum_charge_snapshot')->default(0);
            $table->decimal('quantity_estimated', 8, 3);
            $table->decimal('quantity_actual', 8, 3)->nullable();
            $table->bigInteger('line_estimated_amount');
            $table->bigInteger('line_final_amount')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'service_id']);
        });

        // 3. Order status histories for state machine transitions (PRD 11.5)
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignUuid('actor_user_id')->constrained('users')->onDelete('cascade');
            $table->string('actor_role')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        // 4. Order assignments for courier tasks (PRD 11.5)
        Schema::create('order_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignUuid('courier_id')->constrained('users')->onDelete('cascade');
            $table->string('task_type', 20); // pickup, delivery
            $table->string('status', 20)->default('assigned'); // assigned, accepted, completed, cancelled
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'task_type', 'status']);
            $table->index(['courier_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_assignments');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
