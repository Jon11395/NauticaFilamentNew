<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\CpuLoadHealthCheck\CpuLoadCheck;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DatabaseSizeCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\BackupsCheck;
use Spatie\SecurityAdvisoriesHealthCheck\SecurityAdvisoriesCheck;
use App\Models\GlobalConfig;
use App\Health\Checks\GmailImportScheduleCheck;
use App\Health\Checks\DiskSpaceCheck;
use App\Health\Checks\DirectorySizeCheck;
use App\Mail\Transports\GmailApiTransport;
use Illuminate\Mail\MailManager;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register custom Gmail API mail transport
        $this->app->resolving(MailManager::class, function (MailManager $manager) {
            $manager->extend('gmail-api', function (array $config) {
                return new GmailApiTransport();
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['es','en']); // also accepts a closure
        });

        Filament::serving(function () {
            Filament::registerNavigationGroups([
                NavigationGroup::make()
                     ->label('Proyectos'),
                NavigationGroup::make()
                    ->label('Usuarios'),
                NavigationGroup::make()
                    ->label('Configuraciones')
                    ->collapsed(),
            ]);
        });

        // Set mail from address from GlobalConfig if available
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('global_configs')) {
                $gmailEmail = GlobalConfig::getValue('gmail_user_email');
                if ($gmailEmail) {
                    config(['mail.from.address' => $gmailEmail]);
                }
            }
        } catch (\Exception $e) {
            // Silently fail if database is not available
        }

   

        if (app()->bound('health')) {
            try {
                // Only try to get Gmail config if database is available
                // Wrap in try-catch to prevent recursive logging if DB is unavailable
                $gmailIntervalMinutes = 60; // Default value
                if (\Illuminate\Support\Facades\Schema::hasTable('global_configs')) {
                    $gmailIntervalMinutes = (int) GlobalConfig::getValue('gmail_sync_interval_minutes', 60);
                }
                $gmailHeartbeatMaxAge = max((int) ceil($gmailIntervalMinutes * 1.5), $gmailIntervalMinutes + 5, 10);

                Health::checks([
                    DatabaseCheck::new()->connectionName(config('database.default')),
                    DatabaseSizeCheck::new()->failWhenSizeAboveGb(8),
                    CpuLoadCheck::new()
                        ->failWhenLoadIsHigherInTheLastMinute(12.0)
                        ->failWhenLoadIsHigherInTheLast5Minutes(10.0)
                        ->failWhenLoadIsHigherInTheLast15Minutes(8.0),
                    // Custom disk space check showing df -h results
                    DiskSpaceCheck::new()
                        ->name('Total server disk space'),
                    // Directory size check using du -sh .
                    DirectorySizeCheck::new()
                        ->name('Project directory size'),
                    GmailImportScheduleCheck::new()
                        ->name('Gmail Import Schedule')
                        ->cacheKey('health:schedule:gmail-import')
                        ->heartbeatMaxAgeInMinutes($gmailHeartbeatMaxAge),
                ]);
            } catch (\Exception $e) {
                // Silently fail if database is not available during boot
                // This prevents recursive logging errors
            }
        }
        
    }
}
