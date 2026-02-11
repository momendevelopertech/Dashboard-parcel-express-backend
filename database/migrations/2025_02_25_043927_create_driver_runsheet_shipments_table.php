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
        Schema::create('driver_runsheet_shipments', function (Blueprint $table) {
            $table->id();
            $table->string("shipment_tracking_no");
            $table->foreignId("runsheet_id");
            $table->foreignId("driver_id");
            $table->string("status")->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_runsheet_shipments');
    }
};
