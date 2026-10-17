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
        // 1. Update users table: add role column & composite unique (email, role)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'role')) {
                    $table->enum('role', ['super_admin', 'store_admin', 'courier', 'customer'])
                        ->default('customer')
                        ->after('email')
                        ->index();
                }
            });

            // Populate existing users role based on user_roles pivot if available
            if (Schema::hasTable('user_roles') && Schema::hasTable('roles')) {
                DB::statement("
                    UPDATE users u
                    JOIN user_roles ur ON ur.user_id = u.id
                    JOIN roles r ON r.id = ur.role_id
                    SET u.role = CASE
                        WHEN r.name IN ('platform_owner', 'platform_admin') THEN 'super_admin'
                        WHEN r.name IN ('store_owner', 'store_staff') THEN 'store_admin'
                        WHEN r.name = 'courier' THEN 'courier'
                        ELSE 'customer'
                    END
                ");
            }

            // Drop old unique index on email if it exists, and add composite unique (email, role)
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropUnique(['email']);
                });
            } catch (\Throwable $e) {
                // If drop by array fails, try dropping by name
                try {
                    DB::statement("ALTER TABLE users DROP INDEX users_email_unique");
                } catch (\Throwable $ignored) {}
            }

            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->unique(['email', 'role'], 'users_email_role_unique');
                });
            } catch (\Throwable $ignored) {}
        }

        // 2. Settings table (key-value, cached in Redis/database)
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->string('type', 20)->default('string'); // string, integer, float, boolean, json
                $table->string('group', 50)->default('general'); // general, payment, commission, ai, courier
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 3. COD Collections ledger table
        if (!Schema::hasTable('cod_collections')) {
            Schema::create('cod_collections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();
                $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
                $table->decimal('amount', 12, 2)->default(0);
                $table->enum('status', ['PENDING', 'COLLECTED', 'DEPOSITED', 'RECONCILED'])->default('PENDING');
                $table->string('otp_code', 6)->nullable();
                $table->timestamp('otp_expires_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('deposited_at')->nullable();
                $table->timestamp('reconciled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['store_id', 'status']);
                $table->index(['courier_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cod_collections');
        Schema::dropIfExists('settings');

        if (Schema::hasTable('users')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropUnique('users_email_role_unique');
                    $table->unique('email');
                    $table->dropColumn('role');
                });
            } catch (\Throwable $ignored) {}
        }
    }
};
