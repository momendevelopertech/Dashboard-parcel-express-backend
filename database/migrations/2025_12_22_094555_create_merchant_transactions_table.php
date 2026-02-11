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
        Schema::create('merchant_transactions', function (Blueprint $table) {
            $table->id();

            // Core identification
            $table->foreignId('merchant_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->string('type', 50); // 'delivery_fee', 'return_fee', 'pickup_fee', 'cod_collected', 'settlement', etc.
            $table->string('reference', 100)->nullable(); // Transaction reference/invoice            
            // Related entities (polymorphic approach) - manual columns for index control
            $table->string('transactionable_type')->nullable();
            $table->unsignedBigInteger('transactionable_id')->nullable();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments', 'id')->cascadeOnDelete();
            $table->foreignId('pickup_task_id')->nullable()->constrained('merchant_pickup_tasks', 'id')->cascadeOnDelete();

            // Geographic context
            $table->foreignId('country_id')->nullable()->constrained('countries', 'id')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states', 'id')->nullOnDelete();

            // Financial data
            $table->decimal('amount', 12, 3); // Net amount (positive = credit to merchant, negative = charge)
            $table->decimal('base_amount', 12, 3)->nullable(); // Base fee before discount
            $table->decimal('discount_amount', 12, 3)->default(0); // Discount applied
            $table->string('currency', 3)->default('OMR');

            // Additional context
            $table->string('fee_payer', 20)->nullable(); // 'merchant', 'customer', 'client'
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Flexible storage for type-specific data

            // Payment tracking (for settlements/payments)
            $table->decimal('paid_by_cash', 12, 3)->nullable();
            $table->decimal('paid_by_bank', 12, 3)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_path')->nullable();

            // Status tracking
            $table->string('status', 20)->default('pending'); // 'pending', 'completed', 'cancelled', 'refunded'
            $table->timestamp('completed_at')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['merchant_id', 'type', 'created_at'], 'idx_merchant_type_created');
            $table->index(['merchant_id', 'status'], 'idx_merchant_status');
            $table->index('shipment_id', 'idx_shipment');
            $table->index('pickup_task_id', 'idx_pickup_task');
            $table->index(['transactionable_type', 'transactionable_id'], 'idx_transactionable');
            $table->index('reference', 'idx_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_transactions');
    }
};
