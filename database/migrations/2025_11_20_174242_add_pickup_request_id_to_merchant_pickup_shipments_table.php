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
        Schema::table('merchant_pickup_shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('merchant_pickup_shipments', 'pickup_request_id')) {
                $table->unsignedBigInteger('pickup_request_id')->nullable()->after('merchant_id');
                $table->foreign('pickup_request_id')
                    ->references('id')
                    ->on('pickup_requests')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_pickup_shipments', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_pickup_shipments', 'pickup_request_id')) {
                $table->dropForeign(['pickup_request_id']);
                $table->dropColumn('pickup_request_id');
            }
        });
    }
};
