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
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId("shipment_id")->nullable()->constrained('shipments', 'id')->nullOnDelete();
            $table->string("quantity")->nullable();
            $table->float("price")->nullable();
            $table->string("name")->nullable();
            $table->string("weight")->nullable();
            $table->string("fullName")->nullable();
            $table->string("category")->nullable();
            $table->string("code")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
