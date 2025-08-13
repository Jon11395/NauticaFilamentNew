<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class ProjectContractExpensesOverview extends BaseWidget
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

        $totalExpenses = DB::table('contract_expenses')
            ->join('contracts', 'contract_expenses.contract_id', '=', 'contracts.id')
            ->join('projects', 'contracts.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->sum('contract_expenses.total_deposited');

        $numberofexpenses = DB::table('contract_expenses')
                            ->join('contracts', 'contract_expenses.contract_id', '=', 'contracts.id')
                            ->join('projects', 'contracts.project_id', '=', 'projects.id')
                            ->where('projects.id', $this->record->id)->count();


        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);


        $expenseCountsByMonth = DB::table('contract_expenses')
            ->join('contracts', 'contract_expenses.contract_id', '=', 'contracts.id')
            ->join('projects', 'contracts.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->whereBetween('contract_expenses.date', [$startDate, $endDate])
            ->select(DB::raw('COUNT(*) as count'), DB::raw('MONTH(contract_expenses.date) as month'), DB::raw('YEAR(contract_expenses.date) as year'))
            ->groupBy(DB::raw('YEAR(contract_expenses.date)'), DB::raw('MONTH(contract_expenses.date)'))
            ->orderBy(DB::raw('YEAR(contract_expenses.date)'), 'ASC')
            ->orderBy(DB::raw('MONTH(contract_expenses.date)'), 'ASC')
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
            Stat::make('Gastos por contratos', '₡ '. number_format($totalExpenses, 2))
            ->description($numberofexpenses.' ' .$label)
            ->descriptionIcon('heroicon-c-arrow-trending-down')
            ->chart($expenseCountsByMonth)
            ->color('danger'),
        ];
    }
}
