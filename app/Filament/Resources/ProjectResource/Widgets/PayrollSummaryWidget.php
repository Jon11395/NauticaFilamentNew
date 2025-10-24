<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Carbon\Carbon;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class PayrollSummaryWidget extends BaseWidget
{
    use HasWidgetShield;
    
    public ?Project $record;

    protected static ?string $pollingInterval = '5s';

    protected int | string | array $columnSpan = 6;

    public function getColumns(): int 
    {
        return 1;
    }

    protected ?string $heading = 'Gestión de Nómina';
    protected ?string $description = 'Costos de planillas mensuales y tendencia de gastos laborales';

    protected function getStats(): array
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);

        // Calculate total payroll for current month
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();
        
        $currentMonthPayroll = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->whereBetween('spreadsheets.date', [$currentMonthStart, $currentMonthEnd])
            ->sum('payments.salary');

        // Calculate total payroll for all time
        $totalPayroll = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->sum('payments.salary');

        // Get monthly payroll data for chart
        $monthlyPayrollData = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->whereBetween('spreadsheets.date', [$startDate, $endDate])
            ->select(DB::raw('SUM(payments.salary) as total'), DB::raw('MONTH(spreadsheets.date) as month'), DB::raw('YEAR(spreadsheets.date) as year'))
            ->groupBy(DB::raw('YEAR(spreadsheets.date)'), DB::raw('MONTH(spreadsheets.date)'))
            ->orderBy(DB::raw('YEAR(spreadsheets.date)'), 'ASC')
            ->orderBy(DB::raw('MONTH(spreadsheets.date)'), 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (float) $item->total];
            })
            ->toArray();

        // Calculate average monthly payroll
        $averageMonthlyPayroll = count($monthlyPayrollData) > 0 
            ? round(array_sum($monthlyPayrollData) / count($monthlyPayrollData), 2) 
            : 0;

        return [
            Stat::make('Costo de Nómina', '₡ ' . number_format($currentMonthPayroll, 2))
            ->description('Promedio mensual: ₡' . number_format($averageMonthlyPayroll, 2))
            ->descriptionIcon('heroicon-o-users')
            ->chart($monthlyPayrollData)
            ->color('danger'),
        ];
    }
}
