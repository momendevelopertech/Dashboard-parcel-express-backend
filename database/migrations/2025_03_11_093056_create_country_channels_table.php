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
        Schema::create('country_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId("shipper_id");
            $table->foreignId("internal_country_id")->nullable();
            $table->foreignId("external_country_id")->nullable();
            $table->string("internal_country_name")->nullable();
            $table->string("external_country_name")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_channels');
    }
};
