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
        Schema::create('stock_out_task_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_out_task_id')->constrained();
            $table->string('shipment_tracking_no');
            $table->string('shelf_barcode');
            $table->enum('status', ['pending', 'picked'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_out_task_shipments');
    }
};
