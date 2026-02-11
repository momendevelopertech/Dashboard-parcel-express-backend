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
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'direction')) {
                $table->string('direction')->default('outbound')->after('status');
            }
            if (!Schema::hasColumn('shipments', 'return_kind')) {
                $table->string('return_kind')->nullable()->after('direction');
            }
            if (!Schema::hasColumn('shipments', 'return_to_type')) {
                $table->string('return_to_type')->nullable()->after('return_kind');
            }
            if (!Schema::hasColumn('shipments', 'return_to_id')) {
                $table->unsignedBigInteger('return_to_id')->nullable()->after('return_to_type');
            }
            if (!Schema::hasColumn('shipments', 'return_fee_before_discount')) {
                $table->decimal('return_fee_before_discount', 10, 3)->nullable()->after('delivery_fee_discount');
            }
            if (!Schema::hasColumn('shipments', 'return_fee_discount')) {
                $table->decimal('return_fee_discount', 10, 3)->nullable()->after('return_fee_before_discount');
            }
            if (!Schema::hasColumn('shipments', 'return_fee')) {
                $table->decimal('return_fee', 10, 3)->nullable()->after('return_fee_discount');
            }
            if (!Schema::hasColumn('shipments', 'return_fee_source')) {
                $table->string('return_fee_source')->nullable()->after('return_fee');
            }
            if (!Schema::hasColumn('shipments', 'parent_reverse_shipment_id')) {
                $table->unsignedBigInteger('parent_reverse_shipment_id')->nullable()->after('return_fee_source');
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'direction')) {
                $table->index('direction');
            }
            if (Schema::hasColumn('shipments', 'return_kind')) {
                $table->index('return_kind');
            }
            if (Schema::hasColumn('shipments', 'return_to_type') && Schema::hasColumn('shipments', 'return_to_id')) {
                $table->index(['return_to_type', 'return_to_id']);
            }
            if (Schema::hasColumn('shipments', 'parent_reverse_shipment_id')) {
                $table->index('parent_reverse_shipment_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'parent_reverse_shipment_id')) {
                $table->dropIndex(['parent_reverse_shipment_id']);
                $table->dropColumn('parent_reverse_shipment_id');
            }
            if (Schema::hasColumn('shipments', 'return_fee_source')) {
                $table->dropColumn('return_fee_source');
            }
            if (Schema::hasColumn('shipments', 'return_fee')) {
                $table->dropColumn('return_fee');
            }
            if (Schema::hasColumn('shipments', 'return_fee_discount')) {
                $table->dropColumn('return_fee_discount');
            }
            if (Schema::hasColumn('shipments', 'return_fee_before_discount')) {
                $table->dropColumn('return_fee_before_discount');
            }
            if (Schema::hasColumn('shipments', 'return_to_id')) {
                if (Schema::hasColumn('shipments', 'return_to_type')) {
                    $table->dropIndex(['return_to_type', 'return_to_id']);
                }
                $table->dropColumn('return_to_id');
            }
            if (Schema::hasColumn('shipments', 'return_to_type')) {
                $table->dropColumn('return_to_type');
            }
            if (Schema::hasColumn('shipments', 'return_kind')) {
                $table->dropIndex(['return_kind']);
                $table->dropColumn('return_kind');
            }
            if (Schema::hasColumn('shipments', 'direction')) {
                $table->dropIndex(['direction']);
                $table->dropColumn('direction');
            }
        });
    }
};
