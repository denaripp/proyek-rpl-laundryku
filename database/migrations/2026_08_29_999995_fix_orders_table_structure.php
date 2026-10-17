<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 1. Add missing columns if they don't exist
            if (!Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number')->unique()->after('id');
            }
            if (!Schema::hasColumn('orders', 'store_id')) {
                $table->foreignId('store_id')->nullable()->after('customer_id')->constrained('stores');
            }
            if (!Schema::hasColumn('orders', 'estimated_weight')) {
                $table->decimal('estimated_weight', 8, 2)->nullable()->after('order_number');
            }
            if (!Schema::hasColumn('orders', 'actual_weight')) {
                $table->decimal('actual_weight', 8, 2)->nullable()->after('estimated_weight');
            }
            if (!Schema::hasColumn('orders', 'estimated_price')) {
                $table->decimal('estimated_price', 10, 2)->nullable()->after('actual_weight');
            }
            if (!Schema::hasColumn('orders', 'final_price')) {
                $table->decimal('final_price', 10, 2)->nullable()->after('estimated_price');
            }
            if (!Schema::hasColumn('orders', 'pickup_fee')) {
                $table->decimal('pickup_fee', 10, 2)->nullable()->after('final_price');
            }
            if (!Schema::hasColumn('orders', 'delivery_fee')) {
                $table->decimal('delivery_fee', 10, 2)->nullable()->after('pickup_fee');
            }
            if (!Schema::hasColumn('orders', 'platform_fee')) {
                $table->decimal('platform_fee', 10, 2)->nullable()->after('delivery_fee');
            }
            if (!Schema::hasColumn('orders', 'discount')) {
                $table->decimal('discount', 10, 2)->default(0)->after('platform_fee');
            }
            if (!Schema::hasColumn('orders', 'tax')) {
                $table->decimal('tax', 10, 2)->default(0)->after('discount');
            }
            if (!Schema::hasColumn('orders', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->nullable()->after('tax');
            }
            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('orders', 'courier_id')) {
                $table->foreignId('courier_id')->nullable()->after('payment_status')->constrained('users');
            }
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('courier_id');
            }
            if (!Schema::hasColumn('orders', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users');
            }
            if (!Schema::hasColumn('orders', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('orders', 'customer_notes')) {
                $table->text('customer_notes')->nullable()->after('cancellation_reason');
            }
            if (!Schema::hasColumn('orders', 'store_notes')) {
                $table->text('store_notes')->nullable()->after('customer_notes');
            }
            // Add missing timestamps if any
            if (!Schema::hasColumn('orders', 'courier_assigned_at')) {
                $table->timestamp('courier_assigned_at')->nullable()->after('courier_id');
            }
            if (!Schema::hasColumn('orders', 'courier_arrived_at')) {
                $table->timestamp('courier_arrived_at')->nullable()->after('courier_assigned_at');
            }
            if (!Schema::hasColumn('orders', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable()->after('courier_arrived_at');
            }
            if (!Schema::hasColumn('orders', 'received_by_store_at')) {
                $table->timestamp('received_by_store_at')->nullable()->after('picked_up_at');
            }
            if (!Schema::hasColumn('orders', 'weighed_at')) {
                $table->timestamp('weighed_at')->nullable()->after('received_by_store_at');
            }
            if (!Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('weighed_at');
            }
            if (!Schema::hasColumn('orders', 'processing_started_at')) {
                $table->timestamp('processing_started_at')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('orders', 'ready_at')) {
                $table->timestamp('ready_at')->nullable()->after('processing_started_at');
            }
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('ready_at');
            }
            if (!Schema::hasColumn('orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('delivered_at');
            }
        });

        // Fix foreign key constraints: customer_id should reference users
        Schema::table('orders', function (Blueprint $table) {
            // Drop existing foreign key if it references customers
            $table->dropForeign(['customer_id']);
            // Re-add with correct reference
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Drop package_id column if exists
        if (Schema::hasColumn('orders', 'package_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['package_id']);
                $table->dropColumn('package_id');
            });
        }
    }

    public function down(): void
    {
        // We can't easily revert, but we can drop added columns
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_number', 'store_id', 'estimated_weight', 'actual_weight',
                'estimated_price', 'final_price', 'pickup_fee', 'delivery_fee',
                'platform_fee', 'discount', 'tax', 'total_amount',
                'payment_method', 'payment_status', 'courier_id',
                'cancelled_at', 'cancelled_by', 'cancellation_reason',
                'customer_notes', 'store_notes',
                'courier_assigned_at', 'courier_arrived_at', 'picked_up_at',
                'received_by_store_at', 'weighed_at', 'paid_at',
                'processing_started_at', 'ready_at', 'delivered_at',
                'completed_at',
            ]);
        });
    }
};