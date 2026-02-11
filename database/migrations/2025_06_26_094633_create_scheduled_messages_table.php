<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_title');
            $table->enum('channel', ['sms', 'email', 'whatsapp']);
            $table->text('body');
            $table->json('recipients');
            $table->timestamp('scheduled_at');
            $table->enum('status', ['scheduled', 'sent', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_messages');
    }
};
