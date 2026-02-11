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
        Schema::table('drivers', function (Blueprint $table) {
            $table->boolean('is_on_hold')->default(false)->after('status');
            $table->text('hold_reason')->nullable()->after('is_on_hold');
            $table->foreignId('hold_by')->nullable()->after('hold_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('hold_at')->nullable()->after('hold_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            //
        });
    }
};
