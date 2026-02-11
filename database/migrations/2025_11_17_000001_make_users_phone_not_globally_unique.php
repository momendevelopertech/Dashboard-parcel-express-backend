<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration makes (country_code, phone) no longer globally unique,
     * so that the same phone number can be reused across different roles.
     * Per-role uniqueness is enforced in StoreUserRequest instead.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Some environments may already have this index dropped
            // (e.g. from a previous migration). We guard with a runtime check.
            $indexName = 'users_country_code_phone_unique';

            $hasIndex = collect(DB::select('SHOW INDEX FROM `users` WHERE Key_name = ?', [$indexName]))->isNotEmpty();

            if ($hasIndex) {
                $table->dropUnique(['country_code', 'phone']);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Re-add the unique constraint on (country_code, phone) if it does not exist.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $indexName = 'users_country_code_phone_unique';

            $hasIndex = collect(DB::select('SHOW INDEX FROM `users` WHERE Key_name = ?', [$indexName]))->isNotEmpty();

            if (! $hasIndex) {
                $table->unique(['country_code', 'phone']);
            }
        });
    }
};


