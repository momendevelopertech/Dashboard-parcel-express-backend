<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOnTimeDeliveryReportsTable extends Migration
{
    public function up()
    {
        Schema::create('on_time_delivery_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->integer('total_shipments')->default(0);
            $table->integer('on_time_deliveries')->default(0);
            $table->integer('delayed_deliveries')->default(0);
            $table->decimal('on_time_rate', 5, 2)->default(0.00);
            $table->string('region')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('on_time_delivery_reports');
    }
}
