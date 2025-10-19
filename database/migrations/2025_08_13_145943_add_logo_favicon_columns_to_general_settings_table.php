<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('general_settings')) {
            if (!Schema::hasColumn('general_settings', 'site_logo')) {
                Schema::table('general_settings', function (Blueprint $table) {
                    $table->string('site_logo')->nullable()->after('site_description');
                });
            }
            if (!Schema::hasColumn('general_settings', 'site_favicon')) {
                Schema::table('general_settings', function (Blueprint $table) {
                    $table->string('site_favicon')->nullable()->after('site_logo');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                if (Schema::hasColumn('general_settings', 'site_logo')) {
                    $table->dropColumn('site_logo');
                }
                if (Schema::hasColumn('general_settings', 'site_favicon')) {
                    $table->dropColumn('site_favicon');
                }
            });
        }
    }

};
