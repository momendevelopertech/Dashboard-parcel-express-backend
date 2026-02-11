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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'unassigned_last_seen_at')) {
                $table->timestamp('unassigned_last_seen_at')->nullable()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'unregistered_last_seen_at')) {
                $table->timestamp('unregistered_last_seen_at')->nullable()->after('remember_token');
            }
        });

        // Add indexes safely by wrapping the Schema call in try-catch
        try {
            Schema::table('unassigned_shipments', function (Blueprint $table) {
                 $table->index('created_at');
            });
        } catch (\Throwable $e) {
            // Index likely exists
        }

        try {
            Schema::table('shipments', function (Blueprint $table) {
                 $table->index(['pre_id', 'tracking_no', 'created_at'], 'shipments_unregistered_index');
            });
        } catch (\Throwable $e) {
            // Index likely exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['unassigned_last_seen_at', 'unregistered_last_seen_at']);
        });

        Schema::table('unassigned_shipments', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_unregistered_index');
        });
    }
};
