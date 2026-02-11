<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShipmentFulfillmentReportsTable extends Migration
{
    public function up()
    {
        Schema::create('shipment_fulfillment_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');                 // Date for the report
            $table->string('shipment_type')->nullable();     // Shipment type filter (nullable for all types)
            $table->integer('total_shipments')->default(0);
            $table->integer('fulfilled_shipments')->default(0);
            $table->integer('failed_shipments')->default(0);
            $table->decimal('fulfillment_rate', 5, 2)->default(0.00); // Percentage
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipment_fulfillment_reports');
    }
}
