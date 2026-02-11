<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('automated_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_name');                    // e.g., "Delivery Confirmation"
            $table->enum('trigger_type', ['event', 'time']); // "event" for status change, "time" for schedule
            $table->string('trigger_display');               // Human-readable trigger (e.g., "Shipment Status = Delivered")
            $table->json('trigger_data')->nullable();        // JSON data for trigger details (e.g., {"event":"shipment_status_change","status":"DELIVERED"} or {"cron":"0 8 * * *"})
            $table->enum('action_type', ['send_email','send_sms','status_update']); // Action category
            $table->string('action_display');                // Human-readable action (e.g., "Send Confirmation Email")
            $table->json('action_data')->nullable();         // JSON data for action parameters (e.g., {"template":"delivery_confirm","shipment_id":123})
            $table->enum('status', ['active','inactive'])->default('active'); // Task state
            $table->timestamps();                            // created_at, updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('automated_tasks');
    }
};
