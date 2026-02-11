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
        Schema::create('pickuptask_transactions', function (Blueprint $table) {
            $table->id();
            $table->double("amount");
            $table->unsignedBigInteger("pickuptask_id");
            $table->foreign("pickuptask_id")->references("id")->on("merchant_pickup_tasks");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickuptask_transactions');
    }
};
