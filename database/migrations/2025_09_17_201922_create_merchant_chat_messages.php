// database/migrations/2024_01_01_create_merchant_chat_messages_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('merchant_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_chat_session_id')->constrained()->onDelete('cascade');
            $table->enum('sender_type', ['MERCHANT', 'AGENT', 'SYSTEM']);
            $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('sender_name');
            $table->text('message');
            $table->enum('message_type', ['TEXT', 'FILE', 'IMAGE', 'SYSTEM', 'CARD'])->default('TEXT');
            $table->json('attachments')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('merchant_chat_session_id');
            $table->index(['sender_type', 'sender_id']);
            $table->index('is_read');
        });
    }

    public function down()
    {
        Schema::dropIfExists('merchant_chat_messages');
    }
};
