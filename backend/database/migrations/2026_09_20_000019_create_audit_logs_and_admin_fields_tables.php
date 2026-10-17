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
        // 1. Audit Logs table (PRD 11.8, US-ADMIN-08, FR-ADMIN-09..10)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index(); // STORE_APPROVE, STORE_REJECT, USER_SUSPEND, USER_BAN, USER_UNBAN, CATEGORY_CREATE, CATEGORY_UPDATE, CATEGORY_DELETE, COMMISSION_UPDATE
            $table->string('entity_type', 100)->index(); // Store, User, ServiceCategory, PlatformSetting
            $table->string('entity_id', 100)->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['actor_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'created_at']);
        });

        // 2. Add rejection_reason to stores table if not present
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
        });

        // 3. Add status_reason to users table if not present
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'status_reason')) {
                $table->text('status_reason')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'status_reason')) {
                $table->dropColumn('status_reason');
            }
        });

        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });

        Schema::dropIfExists('audit_logs');
    }
};
