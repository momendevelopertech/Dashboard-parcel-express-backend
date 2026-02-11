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
        Schema::create('merchant_pickup_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_task_id')->nullable()->constrained('merchant_pickup_tasks')->nullOnDelete();
            $table->foreignId('driver_id')->nullable();
            $table->foreignId('merchant_id');
            $table->string('shipment_tracking_no')->nullable();
            $table->string('pre_id')->nullable();
            $table->index('pre_id');
            $table->string('status')->default('to_pickup');
            $table->string('proof_path')->nullable();
            $table->string('proof_path_2')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_pickup_shipments');
    }
};
