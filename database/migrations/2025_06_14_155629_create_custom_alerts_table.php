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
        Schema::create('custom_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('alert_name');
            $table->string('condition');
            $table->timestamp('triggered_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('recipients');
            $table->json('notification_methods');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('custom_alerts');
    }
};
