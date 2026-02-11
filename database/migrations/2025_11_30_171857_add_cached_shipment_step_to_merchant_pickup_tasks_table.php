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
        Schema::table('merchant_pickup_tasks', function (Blueprint $table) {
            $table->unsignedInteger('cached_shipment_step')->default(0)->after('extra_shipments_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_pickup_tasks', function (Blueprint $table) {
            $table->dropColumn('cached_shipment_step');
        });
    }
};
