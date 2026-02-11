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
        Schema::create('driver_runsheet_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId("driver_runsheet_id");
            $table->decimal("total_amount");
            $table->decimal("paid_by_cash")->nullable();
            $table->decimal("paid_by_bank")->nullable();
            $table->decimal("paid_amount");
            $table->foreignId("received_by");
            $table->string("notes")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_runsheet_submissions');
    }
};
