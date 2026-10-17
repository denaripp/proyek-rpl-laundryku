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
        Schema::table('courier_profiles', function (Blueprint $table) {
            $table->foreignUuid('store_id')
                ->nullable()
                ->after('user_id')
                ->constrained('stores')
                ->onDelete('set null');

            $table->text('rejection_reason')
                ->nullable()
                ->after('verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courier_profiles', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn(['store_id', 'rejection_reason']);
        });
    }
};
