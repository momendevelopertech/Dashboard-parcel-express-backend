<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('driver_stops', function (Blueprint $table) {

            $table->binary('image')
                  ->nullable()
                  ->after('longitude');

            $table->integer('package_count')
                  ->default(1)
                  ->after('image');

            // ⚠️ order is a reserved keyword – consider renaming
            $table->enum('order', ['first', 'last', 'auto'])
                  ->default('auto')
                  ->after('package_count');

            $table->enum('type', ['pickup', 'delivery'])
                  ->default('delivery')
                  ->after('order');

            $table->string('arrival_time', 5)
                  ->nullable()
                  ->after('type');

            $table->text('access_instructions')
                  ->nullable()
                  ->after('arrival_time');
        });
    }

    public function down(): void
    {
        Schema::table('driver_stops', function (Blueprint $table) {
            $table->dropColumn([
                'image',
                'package_count',
                'order',
                'type',
                'arrival_time',
                'access_instructions',
            ]);
        });
    }
};
