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
            ->whereNull('project_id')
            ->where('document_type', '!=', 'nota_credito'); // Exclude credit notes

        $totalExpenses = (clone $baseQuery)->count();
        $totalAmount = (clone $baseQuery)->sum('amount');
        $avgAmount = $totalExpenses > 0 ? $totalAmount / $totalExpenses : 0;

        // Credit notes query
        $creditNotesQuery = Expense::query()
            ->where('temporal', true)
            ->whereNull('project_id')
            ->where('document_type', 'nota_credito');

        $totalCreditNotes = (clone $creditNotesQuery)->count();
        $totalCreditAmount = (clone $creditNotesQuery)->sum('amount');

        $cards = [
            Card::make('Gastos temporales', number_format($totalExpenses))
                ->description('₡' . number_format($totalAmount, 2, ',', '.') . ' total por asignar')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];

        // Add credit notes stat if there are any

            $cards[] = Card::make('Notas de crédito', number_format($totalCreditNotes))
                ->description('₡' . number_format($totalCreditAmount, 2, ',', '.') . ' por aplicar')
                ->icon('heroicon-o-minus-circle')
                ->color('danger');


        return $cards;
    }

    protected function getColumns(): int
    {
        return 2;
    }
}


