<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Project;
use Spatie\Activitylog\Models\Activity;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Carbon\Carbon;

class RecentActivityWidget extends BaseWidget
{
    public ?Project $record;

    protected static ?string $pollingInterval = '5s';

    protected int | string | array $columnSpan = 12;

    public function getColumns(): int 
    {
        return 1;
    }

    protected ?string $heading = 'Registro de Actividades';
    protected ?string $description = 'Historial de cambios y eventos recientes del proyecto';

    protected function getStats(): array
    {
        // Get project activities directly
        $projectActivities = Activity::where('subject_type', 'App\Models\Project')
            ->where('subject_id', $this->record->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Get expense activities for this project
        $expenseIds = \App\Models\Expense::where('project_id', $this->record->id)->pluck('id');
        $expenseActivities = Activity::where('subject_type', 'App\Models\Expense')
            ->whereIn('subject_id', $expenseIds)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Get income activities for this project
        $incomeIds = \App\Models\Income::where('project_id', $this->record->id)->pluck('id');
        $incomeActivities = Activity::where('subject_type', 'App\Models\Income')
            ->whereIn('subject_id', $incomeIds)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Get timesheet activities for this project
        $timesheetIds = \App\Models\Timesheet::where('project_id', $this->record->id)->pluck('id');
        $timesheetActivities = Activity::where('subject_type', 'App\Models\Timesheet')
            ->whereIn('subject_id', $timesheetIds)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Combine all activities
        $allActivities = $projectActivities->concat($expenseActivities)
            ->concat($incomeActivities)
            ->concat($timesheetActivities)
            ->sortByDesc('created_at')
            ->take(5);

        // Count activities by type for chart
        $activityCounts = [];
        foreach ($allActivities as $activity) {
            $type = $this->getActivityType($activity);
            $activityCounts[$type] = ($activityCounts[$type] ?? 0) + 1;
        }

        // Get total activities count
        $totalActivities = Activity::where('subject_type', 'App\Models\Project')
            ->where('subject_id', $this->record->id)
            ->count() +
            Activity::where('subject_type', 'App\Models\Expense')
            ->whereIn('subject_id', $expenseIds)
            ->count() +
            Activity::where('subject_type', 'App\Models\Income')
            ->whereIn('subject_id', $incomeIds)
            ->count() +
            Activity::where('subject_type', 'App\Models\Timesheet')
            ->whereIn('subject_id', $timesheetIds)
            ->count();

        // Get activities from last 7 days
        $recentActivityCount = Activity::where('subject_type', 'App\Models\Project')
            ->where('subject_id', $this->record->id)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count() +
            Activity::where('subject_type', 'App\Models\Expense')
            ->whereIn('subject_id', $expenseIds)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count() +
            Activity::where('subject_type', 'App\Models\Income')
            ->whereIn('subject_id', $incomeIds)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count() +
            Activity::where('subject_type', 'App\Models\Timesheet')
            ->whereIn('subject_id', $timesheetIds)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        return [
            Stat::make('Actividades Recientes', $recentActivityCount)
            ->description('Últimos 7 días de ' . $totalActivities . ' actividades totales')
            ->descriptionIcon('heroicon-o-clock')
            ->chart($activityCounts)
            ->color('info'),
        ];
    }

    private function getActivityType($activity): string
    {
        $subjectType = class_basename($activity->subject_type);
        
        switch ($subjectType) {
            case 'Project':
                return 'Proyecto';
            case 'Expense':
                return 'Gastos';
            case 'Income':
                return 'Ingresos';
            case 'Timesheet':
                return 'Horarios';
            case 'Payment':
                return 'Pagos';
            case 'Spreadsheet':
                return 'Planillas';
            default:
                return 'Otros';
        }
    }
}
