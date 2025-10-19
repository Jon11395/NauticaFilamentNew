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
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('hourly_salary', 19,4);
            $table->string('function')->nullable();
            $table->string('account_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('identification')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('country_id');
            $table->foreignId('state_id');
            $table->foreignId('city_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['hourly_salary', 'function', 'account_number', 'phone', 'identification', 'email']);
        });
    }
};
