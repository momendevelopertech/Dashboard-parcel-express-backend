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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_session_id');
            $table->string('sender_type'); // CUSTOMER, AGENT, SYSTEM
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_name')->nullable();
            $table->text('message');
            $table->string('message_type')->default('TEXT'); // TEXT, FILE, IMAGE, SYSTEM
            $table->string('attachments')->nullable(); // JSON array of file paths
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->text('metadata')->nullable(); // JSON for additional data
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
