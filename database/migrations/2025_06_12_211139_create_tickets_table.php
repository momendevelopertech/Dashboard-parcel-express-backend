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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('subject');
            $table->text('description');
            $table->string('category')->nullable(); // Technical, Billing, General, etc.
            $table->string('priority')->default('MEDIUM'); // LOW, MEDIUM, HIGH, URGENT
            $table->string('status')->default('OPEN'); // OPEN, IN_PROGRESS, PENDING, RESOLVED, CLOSED
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->unsignedBigInteger('chat_session_id')->nullable();
            $table->text('resolution')->nullable();
            $table->string('attachments')->nullable(); // JSON array of file paths
            $table->timestamp('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('tags')->nullable(); // JSON for ticket tags
            $table->integer('rating')->nullable(); // Customer satisfaction rating 1-5
            $table->text('feedback')->nullable();
            $table->text('internal_notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
