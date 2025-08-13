<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Income;
use App\Models\Expense;
use App\Filament\Resources\ProjectResource;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;


class AllTrentOverview extends ChartWidget
{
    use HasWidgetShield;
    
    protected static ?string $heading = 'Últimos 12 meses';
    protected static ?int $sort =3;


    protected function getData(): array
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(11);

        // Get expenses counts grouped by year-month
        $expenseCounts = Expense::whereBetween('date', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('MONTH(date) as month'),
                DB::raw('YEAR(date) as year')
            )
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
            ->orderBy(DB::raw('YEAR(date)'), 'ASC')
            ->orderBy(DB::raw('MONTH(date)'), 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => $item->count];
            })
            ->toArray();

        // Get income counts grouped by year-month
        $incomeCounts = Income::whereBetween('date', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('MONTH(date) as month'),
                DB::raw('YEAR(date) as year')
            )
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
            ->orderBy(DB::raw('YEAR(date)'), 'ASC')
            ->orderBy(DB::raw('MONTH(date)'), 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => $item->count];
            })
            ->toArray();
        
        //dump($incomeCounts);
        // Generate labels and data arrays
        $labels = [];
        $expenseData = [];
        $incomeData = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $startDate->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $labels[] = $month->translatedFormat('F'); // Month names, localized

            $expenseData[] = $expenseCounts[$key] ?? 0;
            $incomeData[] = $incomeCounts[$key] ?? 0;
        }

       //dump($incomeData);

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos',  // Income
                    'data' => $incomeData,
                    'borderColor' => '#1C3C6C',
                    'backgroundColor' => '1C3C6C',
                    'pointBackgroundColor' => '#8C9CB4',
                    'hoverBackgroundColor' => '#8C9CB4',
                    'pointHoverBackgroundColor' => '#8C9CB4',
                    'hoverBorderColor' => '#8C9CB4',
                    'fill' => false,
                ],
                [
                    'label' => 'Gastos',  // Expenses
                    'data' => $expenseData,
                    'borderColor' => '#ECAA14',
                    'backgroundColor' => 'ECAA14',            // fill color for points
                    'pointBackgroundColor' => '#CC5500',      // point normal color
                    'pointHoverBackgroundColor' => '#CC5500',// point hover color
                    'hoverBackgroundColor' => '#CC5500',
                    'hoverBorderColor' => '#CC5500',          // line color on hover 
                    'fill' => false,
                ],
                
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
