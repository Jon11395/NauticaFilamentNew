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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->integer('voucher');
            $table->timestamp('date');
            $table->string('concept')->nullable();
            $table->decimal('amount', 19,4);
            $table->enum('type', ['paid', 'unpaid']);
            $table->foreignId('provider_id');
            $table->foreignId('project_id');
            $table->foreignId('expense_type_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
