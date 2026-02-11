<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDriverLocationHistoriesTable extends Migration
{
    public function up()
    {
        Schema::create('driver_location_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id');
            $table->enum('event_type', ['started_route', 'on_route', 'delivery_complete', 'idle', 'stop']);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('event_timestamp')->default(now());
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['driver_id', 'event_timestamp']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_location_histories');
    }
}
