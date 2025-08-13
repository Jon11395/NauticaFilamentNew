<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Project;

class RevenueOverview extends BaseWidget
{
    use HasWidgetShield;

    protected function getStats(): array
    {

        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $thisMonthRevenue = Income::whereBetween('date', [$thisMonth, $thisMonth->copy()->endOfMonth()])->sum('total_deposited');
        $lastMonthRevenue = Income::whereBetween('date', [$lastMonth, $lastMonth->copy()->endOfMonth()])->sum('total_deposited');

        $thisMonthExpenses = Expense::whereBetween('date', [$thisMonth, $thisMonth->copy()->endOfMonth()])->sum('amount');
        $lastMonthExpenses = Expense::whereBetween('date', [$lastMonth, $lastMonth->copy()->endOfMonth()])->sum('amount');

        $totalProjects = Project::where('status','in_progress')->count();


        return [
            Stat::make('Ingresos del mes', '₡ '. number_format($thisMonthRevenue, 2))
                ->description($this->getPercentageChange($thisMonthRevenue,$lastMonthRevenue). ' que el mes pasado')
                ->descriptionIcon($thisMonthRevenue >= $lastMonthRevenue ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($thisMonthRevenue >= $lastMonthRevenue ? 'success' : 'warning'),

            Stat::make('Gastos del mes', '₡ '. number_format($thisMonthExpenses, 2))
                ->description($this->getPercentageChange($thisMonthExpenses,$lastMonthExpenses). ' que el mes pasado')
                ->descriptionIcon($thisMonthExpenses >= $lastMonthExpenses ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-trending-up')
                ->color($thisMonthExpenses >= $lastMonthExpenses ? 'warning' : 'success'),
            
            Stat::make('Proyectos', $totalProjects)
                ->description('Proyectos en progreso')
                ->descriptionIcon('heroicon-o-rectangle-stack')
                ->color('warning'),
        ];
    }

    private function getPercentageChange(float $current, float $previous):string
    {
        if($previous == 0 ) return $current > 0 ? '+100%' : '0%';

        $percentage = (($current - $previous) / $previous) * 100;
        return ($percentage >=0 ? '+' : '') . number_format($percentage, 1). '%';
    }
}
