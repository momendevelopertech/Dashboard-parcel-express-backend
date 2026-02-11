<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->morphs('notifiable');
            $table->unsignedBigInteger('auto_id');
            $table->unique('auto_id');
            $table->text('data')->nullable();
            $table->integer('sort_shipment')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE `notifications` 
    MODIFY `auto_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;');
        // DB::statement('
        //     CREATE TRIGGER notifications_auto_increment 
        //     BEFORE INSERT ON notifications 
        //     FOR EACH ROW
        //     BEGIN
        //         IF NEW.auto_id IS NULL THEN
        //             SET NEW.auto_id = (SELECT COALESCE(MAX(auto_id), 0) + 1 FROM notifications);
        //         END IF;
        //     END
        // ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
