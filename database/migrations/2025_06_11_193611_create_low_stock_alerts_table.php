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
        Schema::create('low_stock_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->integer('minimum_stock_level');
            $table->json('notification_methods');
            $table->enum('status', ['active', 'resolved'])->default('active');
            $table->timestamp('alert_date')->useCurrent();
            $table->timestamps();

            $table->foreign('inventory_item_id')
                ->references('id')->on('inventory_items')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('low_stock_alerts');
    }
};
