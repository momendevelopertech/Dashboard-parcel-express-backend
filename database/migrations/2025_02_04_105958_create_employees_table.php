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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->foreignId('user_id');
            $table->foreignId('position_id');
            $table->foreignId('level_id');
            $table->foreignId('department_id');
            $table->foreignId('direct_manager_id')->nullable()->constrained('users')->nullOnDelete(); // next report for approval
            $table->foreignId('country_id')->nullable()->constrained('countries');
            $table->date('date_of_joining');
            $table->decimal('base_hours', 15, 2);
            $table->decimal('overtime_hour_salary', 15, 2);
            $table->decimal('basic_salary', 15, 2);
            $table->timestamps();
            // $table->unique(['employable_id', 'employable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
