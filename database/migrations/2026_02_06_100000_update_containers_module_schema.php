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
        // 1. Update `containers` table
        Schema::table('containers', function (Blueprint $table) {
            // Identity
            if (!Schema::hasColumn('containers', 'code')) {
                $table->string('code')->after('id')->unique()->nullable(); // Making nullable initially if data exists, or strictly implementation logic
                // If container_number exists, we might want to copy data or drop it? 
                // User said "without affection the many to one relation". keeping existing columns is safer.
            }
            if (!Schema::hasColumn('containers', 'pre_id')) {
                $table->string('pre_id')->nullable()->after('code')->index();
            }
            
            // Polymorphic ownership
            if (!Schema::hasColumn('containers', 'owner_type')) {
                $table->nullableMorphs('owner'); // Adds owner_type and owner_id
            }

            // Routing / Hubs
            if (!Schema::hasColumn('containers', 'from_hub_id')) {
                $table->foreignId('from_hub_id')->nullable()->constrained('hubs')->nullOnDelete(); // Assuming 'hubs' table exists or user generic ID
            }
            if (!Schema::hasColumn('containers', 'current_hub_id')) {
                 $table->foreignId('current_hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            }
            if (!Schema::hasColumn('containers', 'target_hub_id')) {
                 $table->foreignId('target_hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            }
            if (!Schema::hasColumn('containers', 'final_hub_id')) {
                 $table->foreignId('final_hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            }

            // Lifecycle
            // Status might already exist, modifying default if possible or just assuming logic handles it?
            // "status" varchar(191) ... DEFAULT 'OPEN'
            // Existing is "ACTIVE". changing default.
            $table->string('status')->default('OPEN')->change();

            // Sealing / Unloading
            if (!Schema::hasColumn('containers', 'sealed_at')) {
                $table->timestamp('sealed_at')->nullable();
                $table->foreignId('sealed_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('containers', 'unloaded_at')) {
                $table->timestamp('unloaded_at')->nullable();
                $table->foreignId('unloaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();
            }

            // Metadata
            if (!Schema::hasColumn('containers', 'seal_no')) {
                $table->string('seal_no')->nullable();
            }
            // created_by, notes already exist in previous migration
        });

        // 2. Create `container_shipments` table
        if (!Schema::hasTable('container_shipments')) {
            Schema::create('container_shipments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('container_id')->constrained('containers')->cascadeOnDelete();
                $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
                
                $table->timestamp('added_at')->nullable();
                $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
                
                $table->timestamp('removed_at')->nullable();
                $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamp('unloaded_at')->nullable();
                $table->foreignId('unloaded_by')->nullable()->constrained('users')->nullOnDelete();
                
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['container_id', 'removed_at'], 'idx_cs_container_active');
                $table->index(['shipment_id', 'removed_at'], 'idx_cs_shipment_active');
                $table->index(['container_id', 'shipment_id'], 'idx_cs_container_shipment');
            });
        }

        // 3. Update `shipments` table
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'current_container_id')) {
                $table->foreignId('current_container_id')
                      ->nullable()
                      ->constrained('containers')
                      ->nullOnDelete();
                $table->index('current_container_id', 'idx_shipments_current_container');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'current_container_id')) {
                $table->dropForeign(['current_container_id']);
                $table->dropIndex('idx_shipments_current_container');
                $table->dropColumn('current_container_id');
            }
        });

        Schema::dropIfExists('container_shipments');

        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn([
                'code', 'pre_id', 
                'owner_type', 'owner_id', 
                'from_hub_id', 'current_hub_id', 'target_hub_id', 'final_hub_id',
                'sealed_at', 'sealed_by',
                'unloaded_at', 'unloaded_by', 'closed_at',
                'seal_no'
            ]);
            // Revert status default if needed, but risky
        });
    }
};
