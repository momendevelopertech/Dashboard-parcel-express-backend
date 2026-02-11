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
            if (!Schema::hasColumn('users', 'address_revision_last_seen_at')) {
                $table->timestamp('address_revision_last_seen_at')->nullable()->after('remember_token');
            }
        });

        try {
            Schema::table('shipment_address_revisions', function (Blueprint $table) {
                // Check if index exists or just rely on try-catch
                 $table->index('created_at');
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
            $table->dropColumn('address_revision_last_seen_at');
        });

        try {
            Schema::table('shipment_address_revisions', function (Blueprint $table) {
                $table->dropIndex(['created_at']);
            });
        } catch (\Throwable $e) {}
    }
};
