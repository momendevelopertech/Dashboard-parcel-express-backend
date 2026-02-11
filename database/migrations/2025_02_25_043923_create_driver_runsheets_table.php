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
        Schema::create('driver_runsheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId("driver_id");
            $table->string("status")->default('pending');
            $table->timestamp("confirmed_at")->nullable();
            $table->string("holded_at")->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_runsheets');
    }
};
