<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $vendorMigrations = [
            'create_activity_log_table',
            'add_event_column_to_activity_log_table',
            'add_batch_uuid_column_to_activity_log_table',
        ];

        $currentBatch = (int) DB::table('migrations')->max('batch') + 1;

        foreach ($vendorMigrations as $migrationName) {
            $exists = DB::table('migrations')->where('migration', $migrationName)->exists();
            if (! $exists) {
                DB::table('migrations')->insert([
                    'migration' => $migrationName,
                    'batch' => $currentBatch,
                ]);
            }
        }
    }

    public function down(): void
    {
        $vendorMigrations = [
            'create_activity_log_table',
            'add_event_column_to_activity_log_table',
            'add_batch_uuid_column_to_activity_log_table',
        ];

        DB::table('migrations')->whereIn('migration', $vendorMigrations)->delete();
    }
};


