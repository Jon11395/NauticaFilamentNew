<?php

namespace App\Filament\Resources\TemporalExpenseResource\Widgets;

use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class TemporalExpensesStats extends BaseWidget
{
    protected $listeners = ['temporal-expenses-updated' => '$refresh'];

    protected static ?string $pollingInterval = '30s';

    protected function getCards(): array
    {
        $baseQuery = Expense::query()
            ->where('temporal', true)
            ->whereNull('project_id');

        $totalExpenses = (clone $baseQuery)->count();
        $totalAmount = (clone $baseQuery)->sum('amount');
        $avgAmount = $totalExpenses > 0 ? $totalAmount / $totalExpenses : 0;

        return [
            Card::make('Gastos temporales', number_format($totalExpenses))
                ->description('Registros por asignar')
                ->icon('heroicon-o-inbox-stack')
                ->color('warning'),
            Card::make('Monto total', '₡' . number_format($totalAmount, 2, ',', '.'))
                ->description('Suma total por asignar')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }
}


