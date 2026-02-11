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
        Schema::create('abnormalities', function (Blueprint $table) {
            $table->id();
            $table->string("shipment_tracking_no");
            $table->string("type")->nullable();
            $table->string("subtype")->nullable();
            $table->string("notes");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abnormalities');
    }
};
