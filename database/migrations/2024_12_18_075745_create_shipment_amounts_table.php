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
        Schema::create('shipment_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId("shipment_id")->nullable()->constrained("shipments", "id")->nullOnDelete();
            $table->string("amount")->nullable();
            $table->string("display")->nullable();
            $table->string("scale")->nullable();
            $table->float("doubleDisplay")->nullable();
            $table->string("currency")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_amounts');
    }
};
