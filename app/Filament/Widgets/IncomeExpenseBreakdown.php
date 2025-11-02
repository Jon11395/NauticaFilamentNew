<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Income;
use App\Models\Expense;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class IncomeExpenseBreakdown extends ChartWidget
{
    use HasWidgetShield;
    protected static ?string $heading = 'Ingresos vs Gastos';
    protected static ?string $description = 'Comparación entre ingresos totales y gastos pagados/pendientes';
    protected static ?int $sort = 3;
    public ?string $filter = 'this_month';

    protected function getData(): array
    {

        $dateRange = $this->getDateRange();

        $totalIncomeDeposited = Income::whereBetween('date', [$dateRange['start'], $dateRange['end']])->sum('total_deposited');
        $totalExpensesPaid = Expense::where('type', 'paid')
            ->where(function ($q) {
                $q->where('document_type', '!=', 'nota_credito')
                  ->orWhereNull('document_type');
            })
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');
        $totalExpensesUnpaid = Expense::where('type', 'unpaid')
            ->where(function ($q) {
                $q->where('document_type', '!=', 'nota_credito')
                  ->orWhereNull('document_type');
            })
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');

        return [
            'datasets' => [
                [
                    'label' => 'Totales',
                    'data' => [
                        $totalIncomeDeposited,
                        $totalExpensesPaid,
                        $totalExpensesUnpaid,
                    ],
                    'offset' => 3, 
                    'hoverOffset'=> 20,
                    'backgroundColor' => [
                        '#1C3C6C',
                        '#C9CFDB',
                        '#8C9CB4'
                      ],
                    'hoverBorderColor' => '#ECAA14',
                    'hoverBackgroundColor' => '#ECAA14',
                    'borderWidth' => 0
                    
                ],
            ],
            'labels' => [
                'Ingresos',
                'Gastos Pagos',
                'Gastos Pendientes',
            ],

        ];
    }

    

    protected function getType(): string
    {
        return 'doughnut';
    }

    private function getDateRange(): array
    {
        return match ($this->filter){
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
