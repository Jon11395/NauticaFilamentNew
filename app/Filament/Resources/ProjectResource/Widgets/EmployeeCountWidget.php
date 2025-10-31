<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Employee;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Carbon\Carbon;

class EmployeeCountWidget extends BaseWidget
{
    public ?Project $record;

    protected static ?string $pollingInterval = '5s';

    protected int | string | array $columnSpan = ['default' => 12, 'md' => 6];

    public function getColumns(): int 
    {
        return 1;
    }

    protected ?string $heading = 'Recursos Humanos del Proyecto';
    protected ?string $description = 'Personal asignado y actividad laboral en los últimos 6 meses';

    protected function getStats(): array
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);

        // Count total employees assigned to project
        $totalEmployees = DB::table('project_employees')
            ->where('project_id', $this->record->id)
            ->count();

        // Count employees who have worked in the last 30 days
        $activeEmployees = Employee::whereHas('timesheets', function ($query) {
                $query->where('project_id', $this->record->id)
                      ->where('date', '>=', Carbon::now()->subDays(30));
            })
            ->count();

        // Count employees who have been paid in the last 30 days
        $paidEmployees = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->where('spreadsheets.date', '>=', Carbon::now()->subDays(30))
            ->distinct('payments.employee_id')
            ->count('payments.employee_id');

        // Get monthly employee activity for chart
        $monthlyEmployeeActivity = DB::table('timesheets')
            ->where('project_id', $this->record->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->select(DB::raw('COUNT(DISTINCT employee_id) as count'), DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'))
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
            ->orderBy(DB::raw('YEAR(date)'), 'ASC')
            ->orderBy(DB::raw('MONTH(date)'), 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (int) $item->count];
            })
            ->toArray();

        // Calculate average monthly active employees
        $averageMonthlyEmployees = count($monthlyEmployeeActivity) > 0 
            ? round(array_sum($monthlyEmployeeActivity) / count($monthlyEmployeeActivity), 1) 
            : 0;

        return [
            Stat::make('Empleados Activos', $activeEmployees)
            ->description('Promedio mensual: ' . $averageMonthlyEmployees . ' empleados')
            ->descriptionIcon('heroicon-o-user-group')
            ->chart($monthlyEmployeeActivity)
            ->color('info'),
        ];
    }
}
