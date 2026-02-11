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
        Schema::create('admin_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('read_type'); // 'unassigned', 'unregistered', 'address_revision'
            $table->unsignedBigInteger('readable_id'); // ID of the related model
            $table->timestamp('read_at')->useCurrent();
            
            $table->unique(['user_id', 'read_type', 'readable_id'], 'anr_user_type_id_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notification_reads');
    }
};
