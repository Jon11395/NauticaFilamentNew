<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Expense;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectExpenseToPayOverview extends BaseWidget
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

        $totalExpensesUnpaid = number_format(Expense::where('project_id', $this->record->id)->where('type', 'unpaid')->sum('amount'), 2);
        $numberofexpensesUnpaid = Expense::where('project_id', $this->record->id)->where('type', 'unpaid')->count();


        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);

        $expenseCountsUnpaid = Expense::where('project_id', $this->record->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', 'unpaid')
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

        
        if($numberofexpensesUnpaid == 1){
            $label = 'cuenta por pagar';
        }else{
            $label = 'cuentas por pagar';
        }

        return [
            Stat::make('Cuentas por pagar', '₡ '.$totalExpensesUnpaid)
            ->description($numberofexpensesUnpaid.' ' .$label)
            ->descriptionIcon('heroicon-c-arrow-trending-down')
            ->chart($expenseCountsUnpaid)
            ->color('warning'),
        ];
    }


}
