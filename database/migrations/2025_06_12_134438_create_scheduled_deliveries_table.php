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
        Schema::create('scheduled_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('shipment_id')->unique();
            $table->unsignedBigInteger('delivery_slot_id');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'delivered', 'cancelled'])
                ->default('scheduled');
            $table->timestamps();
            $table->foreign('delivery_slot_id')
                ->references('id')->on('delivery_slots')
                ->onDelete('cascade');
            $table->foreign('driver_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('scheduled_deliveries');
    }
};
