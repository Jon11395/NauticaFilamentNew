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
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('additionals', 19,4)->default(0);
            $table->decimal('rebates', 19,4)->default(0);
            $table->decimal('ccss', 19,4)->default(0);
            $table->decimal('deposited', 19,4);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['additionals', 'rebates', 'ccss', 'deposited']);
        });
    }
};
