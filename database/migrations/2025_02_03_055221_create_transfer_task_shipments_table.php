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
        Schema::create('transfer_task_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId("transfer_shipment_id");
            $table->foreignId("transfer_task_id");
            $table->foreignId("transfer_destination_id");
            $table->string("shipment_tracking_no");
            $table->string("truck_barcode");
            $table->string("status")->default('pending');
            $table->timestamp('loaded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_task_shipments');
    }
};
