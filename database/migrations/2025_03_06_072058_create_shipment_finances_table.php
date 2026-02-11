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
        Schema::create('shipment_finances', function (Blueprint $table) {
            $table->id();
            $table->string("shipment_tracking_no")->nullable();
            $table->string('shipment_pre_id')->nullable();
            $table->string("status")->default("pending");
            $table->foreignId("pickup_shipment_id")->nullable();
            $table->foreignId("runsheet_shipment_id")->nullable();
            $table->foreignId("invoice_shipment_id")->nullable();
            $table->foreignId("transfer_task_shipment_id")->nullable();
            $table->decimal('driver_delivery_bonus', 10, 2)->nullable();
            $table->decimal('driver_delivery_fee', 10, 2)->nullable();
            $table->decimal("merchant_balance", 10, 2)->nullable();
            $table->decimal("delivery_fee_paid_by_customer", 10, 2)->nullable();
            $table->timestamps();
            $table->index('shipment_pre_id', 'shipment_finances_pre_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_finances');
    }
};
