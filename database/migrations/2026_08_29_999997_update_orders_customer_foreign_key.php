<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the existing foreign key (named automatically by Laravel)
            $table->dropForeign(['customer_id']);
            // Re-add with reference to users table
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            // Restore original foreign key (if needed) but we'll just drop and not restore.
            // Better to not define down, but we can drop and add back to customers if exists.
            // Since we are changing, we can just drop in down and add to customers if table exists.
            if (Schema::hasTable('customers')) {
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            }
        });
    }
};