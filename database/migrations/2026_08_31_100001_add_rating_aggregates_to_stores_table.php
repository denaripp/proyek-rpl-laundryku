<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('average_rating', 3, 2)->nullable()->after('commission_rate');
            $table->unsignedInteger('review_count')->default(0)->after('average_rating');
            $table->index('average_rating');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['average_rating']);
            $table->dropColumn(['average_rating', 'review_count']);
        });
    }
};