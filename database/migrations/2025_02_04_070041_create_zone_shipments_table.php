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
        Schema::create('zone_shipments', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->foreignId("zone_id");
            $table->string("shipment_tracking_no");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_shipments');
    }
};
