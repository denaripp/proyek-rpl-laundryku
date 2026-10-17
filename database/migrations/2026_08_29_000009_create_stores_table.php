<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->string('phone', 20);
            $table->string('whatsapp', 20)->nullable();
            $table->text('address');
            $table->string('rt_rw', 20)->nullable();
            $table->string('kelurahan', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('city', 100);
            $table->string('province', 100);
            $table->string('postal_code', 10);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->boolean('is_active')->default(false);
            $table->enum('status', ['draft', 'submitted', 'under_review', 'revision_required', 'resubmitted', 'approved', 'rejected', 'suspended'])->default('draft');
            $table->boolean('cod_enabled')->default(false);
            $table->json('operational_hours')->nullable();
            $table->decimal('service_radius_km', 5, 2)->nullable();
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->decimal('pickup_fee', 10, 2)->nullable();
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            // indexes
            $table->index('is_active');
            $table->index('status');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};