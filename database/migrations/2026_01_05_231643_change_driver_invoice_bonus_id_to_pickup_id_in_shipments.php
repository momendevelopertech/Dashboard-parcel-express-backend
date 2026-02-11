<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1️⃣ Add new column
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'driver_invoice_pickup_id')) {
                $table->unsignedBigInteger('driver_invoice_pickup_id')
                    ->nullable()
                    ->after('driver_invoice_bonus_id');
            }
        });

        // 2️⃣ Copy existing data
        DB::statement("
            UPDATE shipments
            SET driver_invoice_pickup_id = driver_invoice_bonus_id
            WHERE driver_invoice_bonus_id IS NOT NULL
        ");

        // 3️⃣ Drop FK + old column
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'driver_invoice_bonus_id')) {
                // drop FK if exists
                try {
                    $table->dropForeign(['driver_invoice_bonus_id']);
                } catch (\Throwable $e) {
                    // ignore if FK doesn't exist
                }

                $table->dropColumn('driver_invoice_bonus_id');
            }
        });

        // 4️⃣ Add new FK
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreign('driver_invoice_pickup_id')
                ->references('id')
                ->on('driver_invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // 1️⃣ Restore old column
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_invoice_bonus_id')->nullable();
        });

        // 2️⃣ Copy data back
        DB::statement("
            UPDATE shipments
            SET driver_invoice_bonus_id = driver_invoice_pickup_id
            WHERE driver_invoice_pickup_id IS NOT NULL
        ");

        // 3️⃣ Drop new FK + column
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['driver_invoice_pickup_id']);
            $table->dropColumn('driver_invoice_pickup_id');

            $table->foreign('driver_invoice_bonus_id')
                ->references('id')
                ->on('driver_invoices')
                ->nullOnDelete();
        });
    }
};
