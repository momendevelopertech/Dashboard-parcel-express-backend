<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('driver_id');
            $table->dateTime('scheduled_delivery_time');
            $table->dateTime('actual_delivery_time')->nullable();
            $table->enum('status', [
                'pending',
                'in_transit',
                'delivered',
                'delayed',
                'exception',
                'cancelled'
            ]);
            $table->date('delivery_date');
            $table->timestamps();

            $table->foreign('driver_id')
                  ->references('id')->on('drivers')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('deliveries');
    }
};
