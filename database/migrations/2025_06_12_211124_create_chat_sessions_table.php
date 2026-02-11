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
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('customer_phone')->nullable();
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->string('status')->default('ACTIVE'); // ACTIVE, CLOSED, ESCALATED
            $table->string('priority')->default('MEDIUM'); // LOW, MEDIUM, HIGH, URGENT
            $table->text('initial_message')->nullable();
            $table->text('tags')->nullable(); // JSON for chat tags
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->integer('rating')->nullable(); // Customer satisfaction rating 1-5
            $table->text('feedback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
