<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customer_feedback', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedTinyInteger('rating')->comment('1–5 stars');
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->foreign('delivery_id')
                  ->references('id')->on('deliveries')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_feedback');
    }
};
