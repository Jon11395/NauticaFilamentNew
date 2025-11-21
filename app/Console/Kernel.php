<?php

namespace App\Console;

use App\Models\GlobalConfig;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('health:schedule-check-heartbeat')
            ->everyMinute()
            ->name('health:schedule-heartbeat');

        $schedule->command('health:queue-check-heartbeat')
            ->everyMinute()
            ->name('health:queue-heartbeat');

        // Delete old expense attachments - run monthly on the 1st at 2 AM
        // Uses retention period from global_configs table (expense_attachment_retention_months)
        $schedule->command('expenses:delete-old-attachments')
            ->monthlyOn(1, '02:00')
            ->name('expenses:delete-old-attachments')
            ->withoutOverlapping();

        $interval = (int) GlobalConfig::getValue('gmail_sync_interval_minutes', 60);

        if ($interval < 60) {
            return;
        }

        if ($interval % 60 !== 0) {
            return;
        }

        $hours = (int) ($interval / 60);

        if ($hours === 1) {
            $cronExpression = '0 * * * *';
        } elseif ($hours < 24) {
            $cronExpression = "0 */{$hours} * * *";
        } elseif ($hours === 24) {
            $cronExpression = '0 0 * * *';
        } elseif ($hours % 24 === 0) {
            $days = (int) ($hours / 24);
            $cronExpression = "0 0 */{$days} * *";
        } else {
            return;
        }

        $schedule->command('gmail:import-receipts')
            ->cron($cronExpression)
            ->name('gmail:import-receipts-auto')
            ->withoutOverlapping()
            ->when(function (): bool {
                return filled(GlobalConfig::getValue('gmail_client_id'))
                    && filled(GlobalConfig::getValue('gmail_client_secret'))
                    && filled(GlobalConfig::getValue('gmail_refresh_token'))
                    && filled(GlobalConfig::getValue('gmail_user_email'));
            })
            ->onSuccess(function () {
                // Update heartbeat when command succeeds
                Cache::store(config('cache.default'))->put('health:schedule:gmail-import', now()->timestamp);
            })
            ->after(function () {
                // Also update heartbeat after completion (regardless of success/failure)
                // This ensures we track that the schedule ran, even if it failed
                Cache::store(config('cache.default'))->put('health:schedule:gmail-import', now()->timestamp);
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

