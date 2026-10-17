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
        Schema::create('store_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('document_type'); // e.g. nib, siup, surat_izin
            $table->string('storage_key');
            $table->string('verification_status')->default('pending')->index(); // pending, approved, rejected
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_documents');
    }
};
