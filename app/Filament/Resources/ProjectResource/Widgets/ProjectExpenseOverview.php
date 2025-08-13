<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Expense;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class ProjectExpenseOverview extends BaseWidget
{

    public ?Project $record;

    protected static ?string $pollingInterval = '5s';

    protected int | string | array $columnSpan = 6;

    public function getColumns(): int 
    {
        return 6;
    }

    protected function getStats(): array
    {

        $totalExpenses = number_format(Expense::where('project_id', $this->record->id)->where('type', 'paid')->sum('amount'), 2);
        $numberofexpenses = Expense::where('project_id', $this->record->id)->where('type', 'paid')->count();


        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);

        $expenseCountsPaid = Expense::where('project_id', $this->record->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', 'paid')
            ->select(DB::raw('COUNT(*) as count'), DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'))
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
            ->orderBy(DB::raw('YEAR(date)'), 'ASC')
            ->orderBy(DB::raw('MONTH(date)'), 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                // Format the key as "year-month" and the value as the count of records
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => $item->count];
            })
            ->toArray();

        
        if($numberofexpenses == 1){
            $label = 'gasto';
        }else{
            $label = 'gastos';
        }

        return [
            Stat::make('Gastos', '₡ '.$totalExpenses)
            ->description($numberofexpenses.' ' .$label)
            ->descriptionIcon('heroicon-c-arrow-trending-down')
            ->chart($expenseCountsPaid)
            ->color('danger'),
        ];
    }

}
