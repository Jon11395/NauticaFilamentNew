<?php

namespace App\Filament\Resources\TemporalExpenseResource\Pages;

use App\Filament\Resources\TemporalExpenseResource;
use App\Filament\Resources\TemporalExpenseResource\Widgets\TemporalExpensesStats;
use Filament\Resources\Pages\ListRecords;

class ListTemporalExpenses extends ListRecords
{
    protected static string $resource = TemporalExpenseResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            TemporalExpensesStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return [
            'md' => 1,
        ];
    }
}
