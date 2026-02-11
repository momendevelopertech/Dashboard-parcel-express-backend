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
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger("driver_deliver_id")->nullable();
            $table->foreign("driver_deliver_id")->references("id")->on("users");

            $table->unsignedBigInteger("driver_pickup_id")->nullable();
            $table->foreign("driver_pickup_id")->references("id")->on("users");

            $table->unsignedBigInteger("merchant_invoice_id")->nullable();
            $table->foreign("merchant_invoice_id")->references("id")->on("merchant_invoices");

            $table->unsignedBigInteger("driver_invoice_bonus_id")->nullable();
            $table->foreign("driver_invoice_bonus_id")->references("id")->on("driver_invoices");

            $table->unsignedBigInteger("driver_invoice_deliver_id")->nullable();
            $table->foreign("driver_invoice_deliver_id")->references("id")->on("driver_invoices");
        });
    }

    /**
     * Reverse the migrations.
     */
public function down(): void
{
    Schema::table('shipments', function (Blueprint $table) {

        // Drop foreign keys
        $table->dropForeign(['driver_deliver_id']);
        $table->dropForeign(['driver_pickup_id']);
        $table->dropForeign(['merchant_invoice_id']);
        $table->dropForeign(['driver_invoice_bonus_id']);
        $table->dropForeign(['driver_invoice_deliver_id']);

        // Drop columns
        $table->dropColumn([
            'driver_deliver_id',
            'driver_pickup_id',
            'merchant_invoice_id',
            'driver_invoice_bonus_id',
            'driver_invoice_deliver_id',
        ]);
    });
}

};
