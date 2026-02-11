<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add column if it doesn't exist
        if (!Schema::hasColumn('merchant_pickup_tasks', 'ref')) {
            Schema::table('merchant_pickup_tasks', function (Blueprint $table) {
                $table->string('ref', 10)->nullable()->after('manifest_id');
            });
        }

        // Populate existing rows with unique ref values (handle both null and empty strings)
        $tasks = DB::table('merchant_pickup_tasks')
            ->where(function($query) {
                $query->whereNull('ref')->orWhere('ref', '');
            })
            ->get();

        foreach ($tasks as $task) {
            do {
                $ref = 'REF-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (DB::table('merchant_pickup_tasks')->where('ref', $ref)->exists());

            DB::table('merchant_pickup_tasks')
                ->where('id', $task->id)
                ->update(['ref' => $ref]);
        }

        // Check if unique constraint already exists
        $indexes = DB::select("SHOW INDEXES FROM merchant_pickup_tasks WHERE Key_name = 'merchant_pickup_tasks_ref_unique'");

        if (empty($indexes)) {
            // Make it not null and add unique constraint
            Schema::table('merchant_pickup_tasks', function (Blueprint $table) {
                DB::statement('ALTER TABLE merchant_pickup_tasks MODIFY ref VARCHAR(10) NOT NULL');
                $table->unique('ref', 'merchant_pickup_tasks_ref_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_pickup_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_pickup_tasks', 'ref')) {
                // Drop unique constraint first
                $table->dropUnique('merchant_pickup_tasks_ref_unique');
                $table->dropColumn('ref');
            }
        });
    }
};
