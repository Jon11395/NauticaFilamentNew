<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Ensure the column exists before altering it
        });

        DB::statement('ALTER TABLE expenses MODIFY expense_type_id BIGINT UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Ensure the column exists before altering it
        });

        DB::statement('ALTER TABLE expenses MODIFY expense_type_id BIGINT UNSIGNED NOT NULL');
    }
};

