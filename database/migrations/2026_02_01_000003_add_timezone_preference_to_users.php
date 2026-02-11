<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // User's preferred timezone for display and operations
            $table->string('timezone', 50)->nullable()->after('email');

            // Index for quick timezone lookups
            $table->index('timezone');
        });

        // Set default timezone for existing users
        DB::table('users')->update(['timezone' => 'Asia/Muscat']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['timezone']);
            $table->dropColumn('timezone');
        });
    }
};
