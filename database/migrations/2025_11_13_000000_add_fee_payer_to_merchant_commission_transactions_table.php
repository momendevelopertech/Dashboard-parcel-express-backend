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
        Schema::table('merchant_commission_transactions', function (Blueprint $table) {
            $table->decimal('shipment_gross_amount', 12, 3)
                ->default(0)
                ->after('delivery_fee');

            $table->decimal('shipment_net_amount', 12, 3)
                ->default(0)
                ->after('shipment_gross_amount');

            $table->enum('fee_payer', ['merchant', 'customer'])
                ->default('merchant')
                ->after('return_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_commission_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'fee_payer',
                'shipment_gross_amount',
                'shipment_net_amount',
            ]);
        });
    }
};

