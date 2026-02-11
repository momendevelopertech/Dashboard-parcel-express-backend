<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduledActionsTable extends Migration
{
    public function up()
    {
        Schema::create('scheduled_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_name');
            $table->string('schedule_display');
            $table->string('cron_expression');
            $table->enum('action_type', ['generate_report', 'send_reminder', 'data_cleanup']);
            $table->string('action_display');
            $table->json('action_payload')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->index('status');
            $table->index('cron_expression');
            $table->index('last_run_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('scheduled_actions');
    }
}
