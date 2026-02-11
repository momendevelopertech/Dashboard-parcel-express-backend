<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('governorate_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId("shipper_id");
            $table->foreignId("internal_governorate_id")->nullable();
            $table->string("internal_governorate_name")->nullable();
            $table->foreignId("external_governorate_id")->nullable();
            $table->string("external_governorate_name")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('governorate_channels');
    }
};
