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
        Schema::create('shelves', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs("owner");
            $table->string('area');
            $table->integer('shelf_number'); 
            $table->integer('layer_number'); 
            $table->integer('partition_number');
            $table->string('barcode')->unique();
            $table->integer('created_by');
            $table->string('location')->nullable();
            // $table->integer('hub_id')->nullable();
            // $table->integer('station_id')->nullable();
            // $table->integer('branch_id')->nullable();
            $table->integer('category_id')->nullable();
            $table->timestamps();
 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shelves');
    }
};
