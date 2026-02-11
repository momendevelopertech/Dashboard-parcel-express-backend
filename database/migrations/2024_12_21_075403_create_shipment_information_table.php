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
        Schema::create('shipment_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId("shipment_id")->nullable()->constrained('shipments', 'id')->nullOnDelete();
            $table->foreignId("merchant_id")->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->foreignId("package_id")->nullable();
            $table->foreignId("zone_id")->nullable()->constrained('zones', 'id')->nullOnDelete(); // Zones
            $table->string("tracking_no")->nullable();
            $table->boolean("in_warehouse")->default(true);
            $table->boolean("lifecycle_end")->default(false);
            // $table->boolean("classification")->nullable();
            // $table->string("batch")->nullable();
            $table->string("type")->nullable();
            $table->string("weight")->nullable();
            $table->string("length")->nullable();
            $table->string("width")->nullable();
            $table->string("height")->nullable();
            $table->integer("status")->nullable();
            $table->foreignId("unit_id")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_information');
    }
};
