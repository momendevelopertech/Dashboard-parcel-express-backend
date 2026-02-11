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
        Schema::create('reverse_pickup_shipments', function (Blueprint $table) {
            $table->id();

            // Foreign key columns (nullable unsignedBigInteger)
            $table->unsignedBigInteger('reverse_pickup_task_id')->nullable();
            $table->unsignedBigInteger('reverse_pickup_request_id')->nullable();
            $table->unsignedBigInteger('reverse_shipment_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();

            $table->enum('status', ['to_pickup', 'picked', 'at_hub', 'delivered_to_merchant'])->default('to_pickup');
            $table->string('pickup_proof')->nullable()->comment('Photo evidence path');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('reverse_pickup_task_id')->references('id')->on('reverse_pickup_tasks')->onDelete('set null');
            $table->foreign('reverse_pickup_request_id')->references('id')->on('reverse_pickup_requests')->onDelete('set null');
            $table->foreign('reverse_shipment_id')->references('id')->on('reverse_shipments')->onDelete('set null');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('merchant_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index(['reverse_pickup_task_id', 'status']);
            $table->index('reverse_shipment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reverse_pickup_shipments');
    }
};
