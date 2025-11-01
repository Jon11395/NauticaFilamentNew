<?php

namespace App\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelHealth\Pages\HealthCheckResults as BaseHealthCheckResults;

class HealthCheckResults extends BaseHealthCheckResults
{
    protected static ?string $navigationGroup = 'Configuraciones';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    public static function getNavigationSort(): ?int
    {
        return static::$navigationSort;
    }

    /**
     * Restrict access using Filament Shield permissions and the plugin's checks.
     */
    public static function canAccess(): bool
    {
        if (! parent::canAccess()) {
            return false;
        }

        return auth()->user()?->can('page_HealthCheckResults') ?? false;
    }
}


