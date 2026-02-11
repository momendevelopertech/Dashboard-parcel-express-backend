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
        Schema::create('shipment_fee_allocation_others', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_fee_allocation_id');
            $table->unsignedBigInteger('warehouse_id')->nullable(); // the "other" branch id
            $table->decimal('amount', 10, 3)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_fee_allocation_others');
    }
};
