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
        Schema::create('delivery_reminders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('shipment_id');                 
            $table->enum('recipient', ['driver', 'customer']);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->timestamp('reminder_time');
            $table->json('methods');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('delivery_reminders');
    }
};
