<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Expense;
use App\Models\ExpenseType;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Illuminate\Support\Facades\DB;

class ExpenseCategoryBreakdown extends ChartWidget
{
    use HasWidgetShield;
    
    protected static ?string $heading = 'Gastos por Categoría';
    protected static ?string $description = 'Distribución de gastos por tipo de categoría en el período seleccionado';
    protected static ?int $sort = 4;
    public ?string $filter = 'this_month';

    protected function getData(): array
    {
        $dateRange = $this->getDateRange();

        // Get expenses grouped by category for the selected period
        $expensesByCategory = Expense::whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->join('expense_types', 'expenses.expense_type_id', '=', 'expense_types.id')
            ->select(
                'expense_types.name as category_name',
                DB::raw('SUM(expenses.amount) as total_amount')
            )
            ->groupBy('expense_types.id', 'expense_types.name')
            ->orderBy('total_amount', 'desc')
            ->get();

        // Prepare data for the chart
        $labels = [];
        $data = [];
        $colors = [
            '#1C3C6C', // Primary dark blue - your brand
            '#ECAA14', // Primary yellow/orange - your brand
            '#C9CFDB', // Light gray-blue - your palette
            '#8C9CB4', // Medium gray-blue - your palette
            '#6E7B95', // Dark gray-blue - your palette
            '#7C6C44', // Brown - your palette
            '#2A4A7C', // Similar dark blue
            '#F2B733', // Similar golden yellow
            '#9CA8C4', // Similar light gray-blue
            '#7A8BA8', // Similar medium gray-blue
            '#5A6B82', // Similar dark gray-blue
            '#8B6F47', // Similar brown
        ];

        foreach ($expensesByCategory as $index => $expense) {
            $labels[] = $expense->category_name;
            $data[] = (float) $expense->total_amount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gastos por Categoría (₡)',
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                    'borderColor' => array_slice($colors, 0, count($data)),
                    'hoverBackgroundColor' => array_slice($colors, 0, count($data)),
                    'hoverOffset' => 20,
                    'borderWidth' => 0
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    private function getDateRange(): array
    {
        return match ($this->filter) {
            'today' => [
                'start' => now()->startOfDay(),
                'end' => now(),
            ],
            'this_week' => [
                'start' => now()->startOfWeek(),
                'end' => now(),
            ],
            'this_month' => [
                'start' => now()->startOfMonth(),
                'end' => now(),
            ],
            'this_year' => [
                'start' => now()->startOfYear(),
                'end' => now(),
            ],
            default => [
                'start' => now()->startOfDay(),
                'end' => now(),
            ],
        };
    }
    
    protected function getFilters(): array
    {
        return [
            'today' => 'Hoy',
            'this_week' => 'Esta semana',
            'this_month' => 'Este mes',
            'this_year' => 'Este año',
        ];
    }
}
