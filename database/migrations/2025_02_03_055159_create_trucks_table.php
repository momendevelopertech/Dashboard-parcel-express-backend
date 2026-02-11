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
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_driver_id')->nullable();
            $table->nullableMorphs("owner");
            $table->string("barcode");
            $table->string("number_plate");
            $table->string("company");
            $table->string("color");
            $table->enum('type', ['truck','van','mini_van','pickup'])->default('truck');
            $table->enum('status', ['active','inactive','in_maintenance'])->default('inactive');
            $table->text("notes")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trucks');
    }
};
