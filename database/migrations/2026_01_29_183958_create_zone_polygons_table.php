<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zone_polygons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zone_id');
            $table->timestamps();

            $table->foreign('zone_id')
                ->references('id')
                ->on('zones')
                ->onDelete('cascade');
        });

        // Add spatial column using raw SQL
        DB::statement("
            ALTER TABLE zone_polygons 
            ADD polygon POLYGON NOT NULL
        ");

        // Spatial index (VERY IMPORTANT for performance)
        DB::statement("
            ALTER TABLE zone_polygons 
            ADD SPATIAL INDEX zone_polygons_polygon_spatial (polygon)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_polygons');
    }
};
