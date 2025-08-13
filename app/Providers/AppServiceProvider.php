<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;


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
        
    }
}
