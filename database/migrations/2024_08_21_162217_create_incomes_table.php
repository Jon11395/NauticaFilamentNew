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
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->integer('bill_number');
            $table->timestamp('date');
            $table->decimal('bill_amount', 19,4);
            $table->decimal('IVA', 19,4);
            $table->decimal('retentions', 19,4);
            $table->string('description')->nullable();
            $table->decimal('total_deposited', 19,2);
            $table->foreignId('project_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
