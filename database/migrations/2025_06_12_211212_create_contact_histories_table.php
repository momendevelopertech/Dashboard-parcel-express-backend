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
        Schema::create('contact_histories', function (Blueprint $table) {
            $table->id();
            $table->string('contact_id'); // Unique identifier for grouping interactions
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('interaction_type'); // CHAT, TICKET, EMAIL, PHONE, OTHER
            $table->string('method'); // CHAT, EMAIL, PHONE, IN_PERSON, OTHER
            $table->string('channel')->nullable(); // WEBSITE, MOBILE_APP, PHONE, EMAIL
            $table->text('summary');
            $table->text('details')->nullable();
            $table->string('status')->nullable(); // RESOLVED, PENDING, FOLLOW_UP_REQUIRED
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->unsignedBigInteger('related_ticket_id')->nullable();
            $table->unsignedBigInteger('related_chat_session_id')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->text('tags')->nullable(); // JSON for categorization
            $table->text('outcome')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->timestamp('follow_up_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_histories');
    }
};
