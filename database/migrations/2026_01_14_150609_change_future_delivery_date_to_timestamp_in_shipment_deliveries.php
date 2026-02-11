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
        Schema::table('shipment_deliveries', function (Blueprint $table) {
            // Change future_delivery_date from date to timestamp
            $table->timestamp('future_delivery_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_deliveries', function (Blueprint $table) {
            // Revert back to date
            $table->date('future_delivery_date')->nullable()->change();
        });
    }
};
