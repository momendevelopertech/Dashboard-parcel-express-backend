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
        Schema::table('pickuptask_transactions', function (Blueprint $table) {
        $table->decimal('paid_by_bank', 8, 2)->nullable();

        $table->decimal('paid_by_cash', 8, 2)->nullable();

        $table->unsignedBigInteger('received_by')->nullable();

        $table->foreign('received_by')
            ->references('id')
            ->on('users')
            ->onDelete('set null');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pickuptask_transactions', function (Blueprint $table) {
            $table->dropForeign(['received_by']);

            $table->dropColumn([
                'paid_by_bank',
                'paid_by_cash',
                'received_by',
            ]);
        });
    }
};
