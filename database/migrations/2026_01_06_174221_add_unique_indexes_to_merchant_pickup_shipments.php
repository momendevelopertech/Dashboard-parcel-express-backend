<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Step 1: Clean up duplicates for (pickup_task_id, shipment_tracking_no)
        // Keep the record with the highest ID for each combination
        DB::statement("
            DELETE t1 FROM merchant_pickup_shipments t1
            INNER JOIN merchant_pickup_shipments t2 
            WHERE 
                t1.pickup_task_id = t2.pickup_task_id 
                AND t1.shipment_tracking_no = t2.shipment_tracking_no
                AND t1.shipment_tracking_no IS NOT NULL
                AND t1.id < t2.id
        ");

        // Step 2: Clean up duplicates for (pickup_task_id, pre_id)
        // Keep the record with the highest ID for each combination
        DB::statement("
            DELETE t1 FROM merchant_pickup_shipments t1
            INNER JOIN merchant_pickup_shipments t2 
            WHERE 
                t1.pickup_task_id = t2.pickup_task_id 
                AND t1.pre_id = t2.pre_id
                AND t1.pre_id IS NOT NULL
                AND t1.id < t2.id
        ");

        // Step 3: Add constraints
        Schema::table('merchant_pickup_shipments', function (Blueprint $table) {

            // Ensure columns exist & are nullable
            $table->string('shipment_tracking_no')->nullable()->change();
            $table->string('pre_id')->nullable()->change();

            // Add unique constraints
            $table->unique(
                ['pickup_task_id', 'shipment_tracking_no'],
                'uniq_pickup_task_tracking_no'
            );

            $table->unique(
                ['pickup_task_id', 'pre_id'],
                'uniq_pickup_task_pre_id'
            );
        });
    }

    public function down(): void
    {
        Schema::table('merchant_pickup_shipments', function (Blueprint $table) {
            $table->dropUnique('uniq_pickup_task_tracking_no');
            $table->dropUnique('uniq_pickup_task_pre_id');
        });
    }
};
