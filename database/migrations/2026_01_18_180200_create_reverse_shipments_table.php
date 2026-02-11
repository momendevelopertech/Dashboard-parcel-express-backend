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
        Schema::create('reverse_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_no', 50)->unique()->comment('Format: RET-PE{YYMMDD}{6-digit-random}');

            // Foreign key columns (nullable unsignedBigInteger)
            $table->unsignedBigInteger('parent_shipment_id')->nullable();
            $table->string('original_tracking_no', 50)->nullable();
            $table->string('type')->default('reverse_pickup');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->text('customer_address')->nullable();
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->unsignedBigInteger('sender_address_id')->nullable();
            $table->unsignedBigInteger('receiver_address_id')->nullable();
            $table->string('status')->default('REVERSE_CREATED');
            $table->json('pricing_calculated')->nullable();
            $table->decimal('driver_commission', 10, 3)->nullable();
            $table->boolean('merchant_fee_charged')->default(false);
            $table->unsignedBigInteger('reverse_pickup_request_id')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('delivered_to_merchant_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('parent_shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('merchant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('sender_address_id')->references('id')->on('addresses')->onDelete('cascade');
            $table->foreign('receiver_address_id')->references('id')->on('addresses')->onDelete('cascade');
            $table->foreign('reverse_pickup_request_id')->references('id')->on('reverse_pickup_requests')->onDelete('set null');

            // Indexes for performance
            $table->index('tracking_no');
            $table->index('parent_shipment_id');
            $table->index(['merchant_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reverse_shipments');
    }
};
