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
        Schema::table('driver_invoices', function (Blueprint $table) {
            // First drop the existing foreign key
            $table->dropForeign(['driver_id']);

            // Then re-add it with cascade delete
            $table->foreign('driver_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_invoices', function (Blueprint $table) {
            // Drop the cascade foreign key
            $table->dropForeign(['driver_id']);

            // Recreate the original foreign key without cascade
            $table->foreign('driver_id')
                  ->references('id')->on('users')
                  ->onDelete('restrict'); // or whatever was original
        });
    }
};
