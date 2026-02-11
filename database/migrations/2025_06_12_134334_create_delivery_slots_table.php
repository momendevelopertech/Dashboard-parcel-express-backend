<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date');                       
            $table->time('start_time');                 
            $table->time('end_time');                   
            $table->integer('capacity');                
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('delivery_slots');
    }
};
