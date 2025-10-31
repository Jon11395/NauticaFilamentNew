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
            // no-op; raw statement below performs the type change
        });

        DB::statement('ALTER TABLE expenses MODIFY voucher VARCHAR(50)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // no-op; raw statement below reverts the type change
        });

        DB::statement('ALTER TABLE expenses MODIFY voucher INT');
    }
};

