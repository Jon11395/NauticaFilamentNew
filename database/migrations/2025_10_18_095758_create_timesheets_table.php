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
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->date('date');
            $table->decimal('hours', 8, 2)->default(0);
            $table->decimal('extra_hours', 8, 2)->default(0);
            $table->boolean('night_work')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Add unique constraint to prevent duplicate entries for same project, employee, and date
            $table->unique(['project_id', 'employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
