<?php

namespace App\Console;

use App\Models\GlobalConfig;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $interval = (int) GlobalConfig::getValue('gmail_sync_interval_minutes', 0);

        $cronExpression = match (true) {
            $interval === 5, $interval === 10, $interval === 15, $interval === 30 => "*/{$interval} * * * *",
            $interval === 60 => '0 * * * *',
            default => null,
        };

        if ($cronExpression === null) {
            return;
        }

        $schedule->command('gmail:import-receipts --limit=25')
            ->cron($cronExpression)
            ->name('gmail:import-receipts-auto')
            ->withoutOverlapping()
            ->when(function (): bool {
                return filled(GlobalConfig::getValue('gmail_client_id'))
                    && filled(GlobalConfig::getValue('gmail_client_secret'))
                    && filled(GlobalConfig::getValue('gmail_refresh_token'))
                    && filled(GlobalConfig::getValue('gmail_user_email'));
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}

