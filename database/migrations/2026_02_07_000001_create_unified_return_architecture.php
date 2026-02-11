<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * This migration implements the unified return architecture:
     * - Drops redundant tables (reverse_shipments, reverse_pickup_shipments, reverse_pickup_transactions)
     * - Renames tables for clarity (reverse_pickup_requests → return_requests, reverse_pickup_tasks → pickup_tasks)
     * - Simplifies shipments table (direction enum → is_return boolean)
     * - Establishes single source of truth for all shipment journeys
     */
    public function up(): void
    {
        // Step 1: Drop redundant tables (Option A: Clean Slate)
        Schema::dropIfExists('reverse_pickup_transactions');
        Schema::dropIfExists('reverse_pickup_shipments');
        Schema::dropIfExists('reverse_shipments');

        // Step 2: Rename tables for clarity
        if (Schema::hasTable('reverse_pickup_requests')) {
            Schema::rename('reverse_pickup_requests', 'return_requests');
        }

        if (Schema::hasTable('reverse_pickup_tasks')) {
            Schema::rename('reverse_pickup_tasks', 'pickup_tasks');
        }

        // Step 3: Update pickup_tasks to link directly to shipments
        Schema::table('pickup_tasks', function (Blueprint $table) {
            // Drop old foreign key if exists
            if (Schema::hasColumn('pickup_tasks', 'reverse_pickup_request_id')) {
                // Check if foreign key exists before dropping
                if ($this->foreignKeyExists('pickup_tasks', 'pickup_tasks_reverse_pickup_request_id_foreign')) {
                    $table->dropForeign(['reverse_pickup_request_id']);
                }
            }

            // Add direct shipment relationship
            if (!Schema::hasColumn('pickup_tasks', 'shipment_id')) {
                $table->unsignedBigInteger('shipment_id')->nullable()->after('id');
                $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            }

            // Add zone_id for per-zone assignment
            if (!Schema::hasColumn('pickup_tasks', 'zone_id')) {
                $table->unsignedBigInteger('zone_id')->nullable()->after('shipment_id');
            }

            // Keep return_request_id for grouping (optional)
            if (!Schema::hasColumn('pickup_tasks', 'return_request_id')) {
                $table->unsignedBigInteger('return_request_id')->nullable()->after('zone_id');
                $table->foreign('return_request_id')->references('id')->on('return_requests')->onDelete('cascade');
            }
        });

        // Step 4: Simplify shipments table - replace direction enum with is_return boolean
        Schema::table('shipments', function (Blueprint $table) {
            // Add is_return boolean
            if (!Schema::hasColumn('shipments', 'is_return')) {
                $table->boolean('is_return')->default(false)->after('status');
            }

            // Rename parent_reverse_shipment_id to return_request_id for clarity
            if (Schema::hasColumn('shipments', 'parent_reverse_shipment_id')) {
                $table->renameColumn('parent_reverse_shipment_id', 'return_request_id');
            } elseif (!Schema::hasColumn('shipments', 'return_request_id')) {
                $table->unsignedBigInteger('return_request_id')->nullable()->after('return_fee_source');
            }
        });

        // Step 5: Migrate existing data from direction to is_return
        if (Schema::hasColumn('shipments', 'direction')) {
            DB::statement("UPDATE shipments SET is_return = (direction = 'return_to_origin')");
        }

        // Step 6: Drop old direction column
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'direction')) {
                // Drop index first
                try {
                    $table->dropIndex(['direction']);
                } catch (\Exception $e) {
                    // Index might not exist
                }
                $table->dropColumn('direction');
            }
        });

        // Step 7: Add indexes for performance
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'is_return') && !$this->indexExists('shipments', 'shipments_is_return_index')) {
                $table->index('is_return');
            }
            if (Schema::hasColumn('shipments', 'return_request_id') && !$this->indexExists('shipments', 'shipments_return_request_id_index')) {
                $table->index('return_request_id');
            }
            // Composite index for common queries - only if return_type column exists
            if (
                Schema::hasColumn('shipments', 'is_return') &&
                Schema::hasColumn('shipments', 'return_type') &&
                !$this->indexExists('shipments', 'shipments_is_return_return_type_index')
            ) {
                $table->index(['is_return', 'return_type']);
            }
        });

        Schema::table('pickup_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('pickup_tasks', 'shipment_id') && !$this->indexExists('pickup_tasks', 'pickup_tasks_shipment_id_index')) {
                $table->index('shipment_id');
            }
            if (Schema::hasColumn('pickup_tasks', 'zone_id') && !$this->indexExists('pickup_tasks', 'pickup_tasks_zone_id_index')) {
                $table->index('zone_id');
            }
        });

        // Step 8: Add foreign key for return_request_id in shipments
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'return_request_id') && Schema::hasTable('return_requests')) {
                try {
                    $table->foreign('return_request_id')->references('id')->on('return_requests')->onDelete('set null');
                } catch (\Exception $e) {
                    // Foreign key might already exist
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign keys
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'return_request_id')) {
                try {
                    $table->dropForeign(['return_request_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
            }
        });

        Schema::table('pickup_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('pickup_tasks', 'shipment_id')) {
                $table->dropForeign(['shipment_id']);
            }
            if (Schema::hasColumn('pickup_tasks', 'return_request_id')) {
                $table->dropForeign(['return_request_id']);
            }
        });

        // Drop indexes
        Schema::table('shipments', function (Blueprint $table) {
            if ($this->indexExists('shipments', 'shipments_is_return_return_type_index')) {
                $table->dropIndex(['is_return', 'return_type']);
            }
            if ($this->indexExists('shipments', 'shipments_return_request_id_index')) {
                $table->dropIndex(['return_request_id']);
            }
            if ($this->indexExists('shipments', 'shipments_is_return_index')) {
                $table->dropIndex(['is_return']);
            }
        });

        Schema::table('pickup_tasks', function (Blueprint $table) {
            if ($this->indexExists('pickup_tasks', 'pickup_tasks_zone_id_index')) {
                $table->dropIndex(['zone_id']);
            }
            if ($this->indexExists('pickup_tasks', 'pickup_tasks_shipment_id_index')) {
                $table->dropIndex(['shipment_id']);
            }
        });

        // Restore direction column
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'direction')) {
                $table->string('direction')->default('outbound')->after('status');
            }
        });

        // Migrate data back
        DB::statement("UPDATE shipments SET direction = CASE WHEN is_return = 1 THEN 'return_to_origin' ELSE 'outbound' END");

        // Drop new columns
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'return_request_id')) {
                $table->renameColumn('return_request_id', 'parent_reverse_shipment_id');
            }
            if (Schema::hasColumn('shipments', 'is_return')) {
                $table->dropColumn('is_return');
            }
        });

        // Restore pickup_tasks structure
        Schema::table('pickup_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('pickup_tasks', 'return_request_id')) {
                $table->dropColumn('return_request_id');
            }
            if (Schema::hasColumn('pickup_tasks', 'zone_id')) {
                $table->dropColumn('zone_id');
            }
            if (Schema::hasColumn('pickup_tasks', 'shipment_id')) {
                $table->dropColumn('shipment_id');
            }
        });

        // Rename tables back
        if (Schema::hasTable('pickup_tasks')) {
            Schema::rename('pickup_tasks', 'reverse_pickup_tasks');
        }

        if (Schema::hasTable('return_requests')) {
            Schema::rename('return_requests', 'reverse_pickup_requests');
        }

        // Note: Cannot restore dropped tables (reverse_shipments, etc.) in rollback
        // This is intentional for clean slate approach
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        $databaseName = DB::connection()->getDatabaseName();

        $result = DB::select(
            "SELECT COUNT(*) as count 
             FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = ? 
             AND TABLE_NAME = ? 
             AND INDEX_NAME = ?",
            [$databaseName, $table, $index]
        );

        return $result[0]->count > 0;
    }

    /**
     * Check if a foreign key exists on a table
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $databaseName = DB::connection()->getDatabaseName();

        $result = DB::select(
            "SELECT CONSTRAINT_NAME 
             FROM information_schema.TABLE_CONSTRAINTS 
             WHERE TABLE_SCHEMA = ? 
             AND TABLE_NAME = ? 
             AND CONSTRAINT_NAME = ? 
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$databaseName, $table, $foreignKey]
        );

        return count($result) > 0;
    }
};
