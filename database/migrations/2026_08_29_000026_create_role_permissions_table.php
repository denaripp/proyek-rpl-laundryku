<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamp('created_at')->nullable();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};