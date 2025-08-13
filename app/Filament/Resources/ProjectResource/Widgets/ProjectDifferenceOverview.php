<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Income;
use App\Models\Expense;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;



class ProjectDifferenceOverview extends BaseWidget
{
    public ?Project $record;

    protected static ?string $pollingInterval = '5s';
  
    protected int | string | array $columnSpan = 12;

    public function getColumns(): int 
    {
        return 12;
    }

    protected function getStats(): array
    {

        $totalIncomeDeposited = Income::where('project_id', $this->record->id)->sum('total_deposited');

        $totalExpensesPaid = Expense::where('project_id', $this->record->id)
            ->where('type', 'paid')
            ->sum('amount');

        
        $totalContractExpenses = DB::table('contract_expenses')
            ->join('contracts', 'contract_expenses.contract_id', '=', 'contracts.id')
            ->join('projects', 'contracts.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->sum('contract_expenses.total_deposited');

        $totalSpreadsheets = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->sum('payments.salary');

            

        $totalDifference = number_format(($totalIncomeDeposited - ($totalExpensesPaid + $totalContractExpenses + $totalSpreadsheets)), 2);


        return [
            Stat::make('Ganancias', '₡ '. $totalDifference)
            ->description('Ingresos - (Gastos Pagos + Contratos + Planillas)')
            ->descriptionIcon('heroicon-o-arrows-right-left')
            ->chart([7, 2, 10, 3, 15, 4, 17])
            ->color('info'),
        ];
    }

}
