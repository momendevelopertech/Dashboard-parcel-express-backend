<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds a unique constraint on (driver_id, shipment_id, action) to prevent
     * duplicate bonus transactions for the same driver/shipment/action combination.
     * 
     * First cleans up any existing duplicate records by keeping only the most recent one.
     */
    public function up(): void
    {
        // Step 1: Clean up existing duplicates before adding constraint
        // Keep the record with the highest ID (most recent) for each combination
        DB::statement("
            DELETE t1 FROM driver_bonuses_transactions t1
            INNER JOIN driver_bonuses_transactions t2 
            WHERE 
                t1.driver_id = t2.driver_id 
                AND (t1.shipment_id = t2.shipment_id OR (t1.shipment_id IS NULL AND t2.shipment_id IS NULL))
                AND (t1.action = t2.action OR (t1.action IS NULL AND t2.action IS NULL))
                AND t1.id < t2.id
        ");

        // Step 2: Add unique constraint
        Schema::table('driver_bonuses_transactions', function (Blueprint $table) {
            // Add unique constraint on driver_id, shipment_id, and action
            // This ensures one bonus per driver per shipment per action type
            $table->unique(
                ['driver_id', 'shipment_id', 'action'],
                'driver_shipment_action_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_bonuses_transactions', function (Blueprint $table) {
            $table->dropUnique('driver_shipment_action_unique');
        });
    }
};

