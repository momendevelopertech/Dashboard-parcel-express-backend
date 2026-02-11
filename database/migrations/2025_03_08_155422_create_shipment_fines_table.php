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
        Schema::create('shipment_fines', function (Blueprint $table) {
            $table->id();
            $table->string("shipment_tracking_no");
            $table->foreignId("driver_id");
            $table->foreignId("created_by");
            $table->decimal("amount", 10, 2);
            $table->string("notes")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_fines');
    }
};
