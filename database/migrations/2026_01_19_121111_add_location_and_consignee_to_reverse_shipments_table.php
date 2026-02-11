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
        Schema::table('reverse_shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('consignee_id')->nullable()->after('customer_address');
            $table->decimal('latitude', 10, 7)->nullable()->after('consignee_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('location_url')->nullable()->after('longitude');

            // Add foreign key
            $table->foreign('consignee_id')->references('id')->on('consignees')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reverse_shipments', function (Blueprint $table) {
            $table->dropForeign(['consignee_id']);
            $table->dropColumn(['consignee_id', 'latitude', 'longitude', 'location_url']);
        });
    }
};
