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
        Schema::table('pickup_missed_shipments_transaction', function (Blueprint $table) {
            if (!Schema::hasColumn('pickup_missed_shipments_transaction', 'note')) {
                $table->text('note')->nullable()->after('driver_id');
            }

            if (!Schema::hasColumn('pickup_missed_shipments_transaction', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('note');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pickup_missed_shipments_transaction', function (Blueprint $table) {
            if (Schema::hasColumn('pickup_missed_shipments_transaction', 'proof_path')) {
                $table->dropColumn('proof_path');
            }

            if (Schema::hasColumn('pickup_missed_shipments_transaction', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
