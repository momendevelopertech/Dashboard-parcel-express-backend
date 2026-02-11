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
        Schema::table('commission_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('id');
            $table->string('owner_type')->nullable()->after('owner_id');

            $table->dropUnique('commission_templates_unique');

            $table->index(['owner_type', 'owner_id'], 'ct_owner_idx');

            $table->unique(
                ['owner_type', 'owner_id', 'country_id', 'state_id'],
                'ct_owner_geo_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_templates', function (Blueprint $table) {
            $table->dropUnique('ct_owner_geo_unique');
            $table->unique(['country_id', 'state_id'], 'commission_templates_unique');

            $table->dropIndex('ct_owner_idx');

            $table->dropColumn(['owner_id', 'owner_type']);
        });
    }
};
