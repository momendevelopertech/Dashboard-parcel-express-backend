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
        Schema::create('reverse_pickup_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 20)->unique();

            // Foreign key columns (nullable unsignedBigInteger)
            $table->unsignedBigInteger('original_shipment_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->unsignedBigInteger('pickup_address_id')->nullable();
            $table->unsignedBigInteger('delivery_address_id')->nullable();

            $table->integer('no_of_shipments')->default(0);
            $table->integer('picked_shipments_no')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->json('pricing_calculated')->nullable();
            $table->text('note')->nullable();
            $table->nullableMorphs('owner');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('original_shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('merchant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('pickup_address_id')->references('id')->on('addresses')->onDelete('cascade');
            $table->foreign('delivery_address_id')->references('id')->on('addresses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reverse_pickup_requests');
    }
};
