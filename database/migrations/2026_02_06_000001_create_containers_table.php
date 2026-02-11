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
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->string('container_number')->unique()->index();
            $table->string('tracking_no')->nullable()->unique()->index();
            $table->string('container_type')->nullable(); // e.g., BOX, PALLET, BAG, etc.
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->decimal('current_weight', 10, 2)->default(0);
            $table->decimal('max_volume', 10, 2)->nullable();
            $table->decimal('current_volume', 10, 2)->default(0);
            $table->integer('shipment_count')->default(0);
            $table->string('status')->default('ACTIVE'); // ACTIVE, FULL, IN_TRANSIT, DELIVERED, ARCHIVED
            $table->nullableMorphs("facility"); // Can belong to hub, branch, etc.
            $table->foreignId('created_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'facility_type', 'facility_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
