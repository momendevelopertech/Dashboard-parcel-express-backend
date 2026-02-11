<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('addresses', 'is_verified'))
                $table->boolean('is_verified')->default(false)->index();
            if (!Schema::hasColumn('addresses', 'verification_method'))
                $table->string('verification_method')->nullable()->index();
            if (!Schema::hasColumn('addresses', 'proof_url'))
                $table->text('proof_url')->nullable();
            if (!Schema::hasColumn('addresses', 'proof_note'))
                $table->text('proof_note')->nullable();
            if (!Schema::hasColumn('addresses', 'verified_at'))
                $table->timestamp('verified_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            //
        });
    }
};
