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
        // 1. Complaints Table (FR-COM-01, FR-COM-02, FR-COM-03, PRD 11.7)
        Schema::create('complaints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignUuid('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('category', 50); // DAMAGED_ITEM, LOST_ITEM, LATE_DELIVERY, SERVICE_QUALITY, INCORRECT_BILLING, OTHER
            $table->text('description');
            $table->string('status', 30)->default('OPEN')->index(); // OPEN, IN_INVESTIGATION, RESOLVED, REJECTED
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['store_id', 'status']);
        });

        // 2. Complaint Attachments Table (FR-COM-02, PRD 11.7)
        Schema::create('complaint_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('complaint_id')->constrained('complaints')->onDelete('cascade');
            $table->string('storage_key');
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();

            $table->index(['complaint_id']);
        });

        // 3. Ratings Table (FR-RATE-01, FR-RATE-02, FR-RATE-03, PRD 11.7)
        Schema::create('ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Constraint: one active rating per customer per order (unique constraint on order_id)
            $table->foreignUuid('order_id')->unique()->constrained('orders')->onDelete('cascade');
            $table->foreignUuid('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('store_id')->constrained('stores')->onDelete('cascade');
            $table->unsignedTinyInteger('score'); // 1 to 5
            $table->text('review')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'score']);
            $table->index(['customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('complaint_attachments');
        Schema::dropIfExists('complaints');
    }
};
