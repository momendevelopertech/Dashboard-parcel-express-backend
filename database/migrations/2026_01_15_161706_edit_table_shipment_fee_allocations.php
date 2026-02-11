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
        Schema::table('shipment_fee_allocations', function (Blueprint $table) {
            $table->string('shipment_tracking_no')->nullable()->change();
            $table->string('pre_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_fee_allocations', function (Blueprint $table) {
            $table->string('shipment_tracking_no')->nullable(false)->change();
            $table->dropColumn('pre_id');
        });
    }
};
