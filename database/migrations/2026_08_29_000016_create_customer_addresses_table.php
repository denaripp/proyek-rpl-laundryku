<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('label', 50)->nullable();
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->text('address');
            $table->string('rt_rw', 20)->nullable();
            $table->string('kelurahan', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('city', 100);
            $table->string('province', 100);
            $table->string('postal_code', 10);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('address_note')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};