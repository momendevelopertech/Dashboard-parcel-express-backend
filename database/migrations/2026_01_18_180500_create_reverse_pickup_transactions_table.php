<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reverse_pickup_transactions', function (Blueprint $table) {
            $table->id();

            // Foreign key columns (nullable unsignedBigInteger)
            $table->unsignedBigInteger('reverse_shipment_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->enum('user_type', ['driver', 'merchant'])->nullable();
            $table->enum('transaction_type', ['driver_commission_credit', 'merchant_fee_debit'])->nullable();
            $table->decimal('amount', 10, 3)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('executed_at')->nullable();
            $table->nullableMorphs('reference');
            $table->text('note')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('reverse_shipment_id')->references('id')->on('reverse_shipments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance (explicit short names due to MySQL 64-char limit)
            $table->index(['reverse_shipment_id', 'transaction_type'], 'rev_txn_ship_type_idx');
            $table->index(['user_id', 'user_type'], 'rev_txn_user_idx');
            $table->index('status', 'rev_txn_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reverse_pickup_transactions');
    }
};
