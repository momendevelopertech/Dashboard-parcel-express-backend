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
        DB::statement("
            UPDATE merchants
            SET image = CASE
                WHEN image IS NULL OR image = '' THEN NULL
                WHEN image LIKE '[%' THEN image
                ELSE JSON_ARRAY(image)  -- Wrap single URL in array
            END
        ");

        Schema::table('merchants', function (Blueprint $table) {
            $table->json('image')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
        });

        DB::statement("
            UPDATE merchants
            SET image = CASE
                WHEN image IS NULL THEN NULL
                ELSE JSON_UNQUOTE(JSON_EXTRACT(image, '$[0]'))
            END
        ");
    }
};
