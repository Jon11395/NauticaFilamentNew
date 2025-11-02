<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\CarbonInterval;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TemporalExpensesSummary extends BaseWidget
{
    use HasWidgetShield;

    protected $listeners = ['temporal-expenses-updated' => '$refresh'];

    protected ?string $heading = 'Resumen de gastos temporales';
    protected ?string $description = 'Backlog actual y tasa de resolución mensual de los gastos temporales';
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->copy()->endOfMonth();

        $pendingTemporalExpenses = Expense::query()
            ->where('temporal', true)
            ->where(function ($q) {
                $q->where('document_type', '!=', 'nota_credito')
                  ->orWhereNull('document_type');
            })
            ->get(['created_at']);

        $pendingTemporalCount = $pendingTemporalExpenses->count();

        $averagePendingSeconds = $pendingTemporalCount > 0
            ? (int) round(
                $pendingTemporalExpenses->avg(
                    fn (Expense $expense): int => $expense->created_at->diffInSeconds()
                )
            )
            : null;

        $averagePendingHuman = $averagePendingSeconds !== null
            ? CarbonInterval::seconds($averagePendingSeconds)
                ->cascade()
                ->forHumans(['short' => true, 'parts' => 2])
            : 'Sin pendientes';

        $assignedTemporalThisMonth = Expense::query()
            ->where('temporal', false)
            ->whereNotNull('project_id')
            ->where(function ($q) {
                $q->where('document_type', '!=', 'nota_credito')
                  ->orWhereNull('document_type');
            })
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->count();

        $clearanceRateDenominator = $assignedTemporalThisMonth + $pendingTemporalCount;
        $temporalClearanceRate = $clearanceRateDenominator > 0
            ? ($assignedTemporalThisMonth / $clearanceRateDenominator) * 100
            : 0;

        return [
            Stat::make('Temporales pendientes', number_format($pendingTemporalCount))
                ->description('Gastos por asignar')
                ->descriptionIcon($pendingTemporalCount > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check')
                ->color($pendingTemporalCount > 0 ? 'warning' : 'success'),

            Stat::make('Tiempo promedio pendiente', $averagePendingHuman)
                ->description($pendingTemporalCount > 0
                    ? ('Basado en ' . number_format($pendingTemporalCount) . ' gastos')
                    : 'Sin gastos temporales activos'
                )
                ->descriptionIcon('heroicon-o-clock')
                ->color('info'),

            Stat::make('Índice de resolución temporal', number_format($temporalClearanceRate, 1) . '%')
                ->description($assignedTemporalThisMonth . ' asignados este mes')
                ->descriptionIcon($temporalClearanceRate >= 75 ? 'heroicon-o-check-badge' : 'heroicon-o-arrow-path')
                ->color($temporalClearanceRate >= 75 ? 'success' : 'warning'),
        ];
    }
}

