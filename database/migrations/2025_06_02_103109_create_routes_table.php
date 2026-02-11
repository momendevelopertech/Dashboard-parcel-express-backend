<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoutesTable extends Migration
{
    public function up()
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_code')->unique();
            $table->date('route_date');
            $table->unsignedBigInteger('driver_id');
            $table->string('start_location')->nullable();
            $table->string('end_location')->nullable();
            $table->decimal('estimated_distance', 10, 2)->nullable();
            $table->integer('estimated_duration')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed'])->default('planned');
            $table->string('polyline')->nullable();
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('route_date');
            $table->index('driver_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('routes');
    }
}
