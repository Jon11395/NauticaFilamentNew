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
        if (!Schema::hasColumn('expenses', 'clave')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('clave', 100)->nullable()->after('voucher');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('expenses', 'clave')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('clave');
            });
        }
    }
};

