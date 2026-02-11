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
        Schema::create('waybill_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_user_id')->constrained('users');
            $table->unsignedInteger('shipments_count');
            $table->dateTime('scheduled_at');
            $table->enum('status', ['pending', 'assigned', 'completed', 'canceled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waybill_requests');
    }
};
