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
        Schema::create('shipment_fee_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_tracking_no')->unique();

            $table->unsignedBigInteger('first_warehouse_id')->nullable();
            $table->unsignedBigInteger('other_warehouse_id')->nullable();
            $table->unsignedBigInteger('pickup_driver_id')->nullable();
            $table->unsignedBigInteger('delivery_driver_id')->nullable();

            $table->decimal('pickup_driver_amount', 10, 3)->nullable()->default(0);
            $table->decimal('first_warehouse_amount', 10, 3)->nullable()->default(0);
            $table->decimal('other_warehouse_amount', 10, 3)->nullable()->default(0);
            $table->decimal('delivery_driver_amount', 10, 3)->nullable()->default(0);
            $table->decimal('company_amount', 10, 3)->nullable()->default(0);

            $table->decimal('total_delivery_fee', 10, 3)->nullable()->default(0);

          
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_fee_allocations');
    }
};
