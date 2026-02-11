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
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('value', 12, 3)->nullable()->change();
            $table->decimal('total_cod', 12, 3)->nullable()->change();
            $table->decimal('delivery_fee', 12, 3)->nullable()->change();

            $table->decimal('delivery_fee_before_discount', 12, 3)->nullable()->after('delivery_fee');
            $table->decimal('delivery_fee_discount', 12, 3)->nullable()->after('delivery_fee_before_discount');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            //
        });
    }
};
