// database/migrations/2024_01_01_create_merchant_chat_sessions_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('merchant_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->onDelete('cascade');
            $table->string('session_id')->unique();
            $table->string('subject')->default('General Chat');
            $table->enum('status', ['ACTIVE', 'CLOSED', 'ARCHIVED'])->default('ACTIVE');
            $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])->default('MEDIUM');
            $table->text('initial_message')->nullable();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->integer('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index('session_id');
            $table->index('assigned_agent_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('merchant_chat_sessions');
    }
};
