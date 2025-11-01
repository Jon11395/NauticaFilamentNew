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


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
                     ->label('Proyectos')
                     ->icon('heroicon-o-home-modern'),
                NavigationGroup::make()
                    ->label('Usuarios')
                    ->icon('heroicon-s-user-group'),
                NavigationGroup::make()
                    ->label('Configuraciones')
                    ->icon('heroicon-s-cog')
                    ->collapsed(),
            ]);
        });

   

        $gmailIntervalMinutes = (int) GlobalConfig::getValue('gmail_sync_interval_minutes', 60);
        $gmailHeartbeatMaxAge = max((int) ceil($gmailIntervalMinutes * 1.5), $gmailIntervalMinutes + 5, 10);

        Health::checks([
            DatabaseCheck::new()->connectionName(config('database.default')),
            DatabaseSizeCheck::new()->failWhenSizeAboveGb(8),
            CpuLoadCheck::new()
                ->failWhenLoadIsHigherInTheLastMinute(12.0)
                ->failWhenLoadIsHigherInTheLast5Minutes(10.0)
                ->failWhenLoadIsHigherInTheLast15Minutes(8.0),
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(75)
                ->failWhenUsedSpaceIsAbovePercentage(90),
            ScheduleCheck::new()
                ->name('Gmail Import Schedule')
                ->cacheKey('health:schedule:gmail-import')
                ->heartbeatMaxAgeInMinutes($gmailHeartbeatMaxAge),
        ]);
        
    }
}
