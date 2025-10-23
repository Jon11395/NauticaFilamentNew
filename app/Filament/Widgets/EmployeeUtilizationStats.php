<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use App\Models\Project;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Employee;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class EmployeeUtilizationStats extends BaseWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Utilización de Recursos Humanos';
    protected ?string $description = 'Métricas de empleados activos, horas trabajadas y tasa de utilización';
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // Get all active projects with their profitability
        $allProjects = Project::where('status', 'in_progress')
            ->with(['incomes', 'expenses'])
            ->get()
            ->map(function ($project) {
                $totalIncome = $project->incomes->sum('total_deposited');
                $totalExpenses = $project->expenses->sum('amount');
                $profit = $totalIncome - $totalExpenses;
                $profitMargin = $totalIncome > 0 ? ($profit / $totalIncome) * 100 : 0;
                
                return [
                    'project' => $project,
                    'total_income' => $totalIncome,
                    'total_expenses' => $totalExpenses,
                    'profit' => $profit,
                    'profit_margin' => $profitMargin,
                ];
            });

        if ($allProjects->isEmpty()) {
            return [
                Stat::make('Proyecto Más Rentable', 'Sin proyectos')
                    ->description('No hay proyectos activos')
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color('gray'),
            ];
        }

        // Get most profitable project
        $mostProfitable = $allProjects->sortByDesc('profit')->first();
        $project = $mostProfitable['project'];
        $profit = $mostProfitable['profit'];
        $profitMargin = $mostProfitable['profit_margin'];

        // Calculate average profitability
        $totalProfit = $allProjects->sum('profit');
        $totalIncome = $allProjects->sum('total_income');
        $averageProfit = $allProjects->count() > 0 ? $totalProfit / $allProjects->count() : 0;
        $averageProfitMargin = $totalIncome > 0 ? ($totalProfit / $totalIncome) * 100 : 0;

        // Get total active projects count
        $totalActiveProjects = $allProjects->count();

        // Employee & Resource Stats
        $activeEmployees = Employee::where('active', true)->count();
        $thisMonth = now()->startOfMonth();
        $totalHoursThisMonth = Timesheet::whereBetween('date', [$thisMonth, $thisMonth->copy()->endOfMonth()])
            ->sum(DB::raw('hours + extra_hours'));
        
        // Calculate employee utilization rate (assuming 160 hours per month per employee)
        $totalPossibleHours = $activeEmployees * 160;
        $utilizationRate = $totalPossibleHours > 0 ? ($totalHoursThisMonth / $totalPossibleHours) * 100 : 0;

        return [
            Stat::make('Empleados Activos', $activeEmployees)
                ->description('Personal disponible')
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),

            Stat::make('Horas Trabajadas', number_format($totalHoursThisMonth, 0))
                ->description('Este mes')
                ->descriptionIcon('heroicon-o-clock')
                ->color('primary'),

            Stat::make('Utilización', number_format($utilizationRate, 1) . '%')
                ->description('Capacidad utilizada')
                ->descriptionIcon($utilizationRate >= 80 ? 'heroicon-o-chart-bar' : 'heroicon-o-chart-bar-square')
                ->color($utilizationRate >= 80 ? 'success' : ($utilizationRate >= 60 ? 'warning' : 'danger')),
        ];
    }
}
