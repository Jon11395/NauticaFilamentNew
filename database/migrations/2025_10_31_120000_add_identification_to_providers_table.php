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
        if (! Schema::hasColumn('providers', 'identification')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->string('identification')->nullable()->unique()->after('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('providers', 'identification')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->dropUnique('providers_identification_unique');
                $table->dropColumn('identification');
            });
        }
    }
};

