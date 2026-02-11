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
     * This migration adds polymorphic hub information fields to track:
     * - final_owner (ultimate destination, set at creation, IMMUTABLE)
     * - current_owner (physical location now, updated by operations)
     * - from_owner (previous location before current)
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Final Hub (IMMUTABLE - ultimate destination from zone)
            $table->string('final_owner_type')->nullable()->after('destination_owner_type');
            $table->unsignedBigInteger('final_owner_id')->nullable()->after('final_owner_type');
            
            // Current Hub (DYNAMIC - where shipment is physically located NOW)
            $table->string('current_owner_type')->nullable()->after('final_owner_id');
            $table->unsignedBigInteger('current_owner_id')->nullable()->after('current_owner_type');
            
            // Previous Hub (FROM - where shipment came from)
            $table->string('from_owner_type')->nullable()->after('current_owner_id');
            $table->unsignedBigInteger('from_owner_id')->nullable()->after('from_owner_type');
            
            // Add indexes for performance
            $table->index(['current_owner_type', 'current_owner_id'], 'idx_shipments_current_owner');
            $table->index(['final_owner_type', 'final_owner_id'], 'idx_shipments_final_owner');
            $table->index(['from_owner_type', 'from_owner_id'], 'idx_shipments_from_owner');
        });
        
        // Backfill existing data
        // Final owner = destination_owner (what was set at creation)
        // Current owner = owner (what's being used as current location)
        // From owner = NULL (no historical data available)
        DB::statement('
            UPDATE shipments 
            SET 
                final_owner_type = destination_owner_type,
                final_owner_id = destination_owner_id,
                current_owner_type = owner_type,
                current_owner_id = owner_id
            WHERE destination_owner_type IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('idx_shipments_current_owner');
            $table->dropIndex('idx_shipments_final_owner');
            $table->dropIndex('idx_shipments_from_owner');
            
            $table->dropColumn([
                'final_owner_type',
                'final_owner_id',
                'current_owner_type',
                'current_owner_id',
                'from_owner_type',
                'from_owner_id',
            ]);
        });
    }
};

