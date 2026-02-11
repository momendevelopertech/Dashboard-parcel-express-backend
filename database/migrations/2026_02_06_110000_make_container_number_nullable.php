<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            if (Schema::hasColumn('containers', 'container_number')) {
                $table->string('container_number')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Irreversible safely without data loss guarantee, leaving as nullable is fine
    }
};
