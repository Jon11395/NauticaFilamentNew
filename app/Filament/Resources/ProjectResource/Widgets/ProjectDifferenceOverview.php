<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Income;
use App\Models\Expense;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Carbon\Carbon;



class ProjectDifferenceOverview extends BaseWidget
{
    public ?Project $record;

    protected static ?string $pollingInterval = '5s';

    protected int | string | array $columnSpan = 12;

    public function getColumns(): int 
    {
        return 1;
    }

    protected ?string $heading = 'Análisis de Rentabilidad';
    protected ?string $description = 'Diferencia entre ingresos y gastos totales con tendencia mensual';

    protected function getStats(): array
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);

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

        // Calculate monthly differences for chart
        $monthlyDifferences = [];
        
        // Get monthly incomes
        $monthlyIncomes = Income::where('project_id', $this->record->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->select(DB::raw('SUM(total_deposited) as total'), DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'))
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (float) $item->total];
            })
            ->toArray();

        // Get monthly expenses
        $monthlyExpenses = Expense::where('project_id', $this->record->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', 'paid')
            ->select(DB::raw('SUM(amount) as total'), DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'))
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (float) $item->total];
            })
            ->toArray();

        // Get monthly contract expenses
        $monthlyContractExpenses = DB::table('contract_expenses')
            ->join('contracts', 'contract_expenses.contract_id', '=', 'contracts.id')
            ->join('projects', 'contracts.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->whereBetween('contract_expenses.date', [$startDate, $endDate])
            ->select(DB::raw('SUM(contract_expenses.total_deposited) as total'), DB::raw('MONTH(contract_expenses.date) as month'), DB::raw('YEAR(contract_expenses.date) as year'))
            ->groupBy(DB::raw('YEAR(contract_expenses.date)'), DB::raw('MONTH(contract_expenses.date)'))
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (float) $item->total];
            })
            ->toArray();

        // Get monthly spreadsheet expenses
        $monthlySpreadsheetExpenses = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->whereBetween('spreadsheets.date', [$startDate, $endDate])
            ->select(DB::raw('SUM(payments.salary) as total'), DB::raw('MONTH(spreadsheets.date) as month'), DB::raw('YEAR(spreadsheets.date) as year'))
            ->groupBy(DB::raw('YEAR(spreadsheets.date)'), DB::raw('MONTH(spreadsheets.date)'))
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (float) $item->total];
            })
            ->toArray();

        // Calculate differences for each month
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $key = $currentDate->format('Y-m');
            
            $income = $monthlyIncomes[$key] ?? 0;
            $expenses = ($monthlyExpenses[$key] ?? 0) + 
                       ($monthlyContractExpenses[$key] ?? 0) + 
                       ($monthlySpreadsheetExpenses[$key] ?? 0);
            
            $monthlyDifferences[$key] = round($income - $expenses, 2);
            
            $currentDate->addMonth();
        }

        $totalDifference = number_format(($totalIncomeDeposited - ($totalExpensesPaid + $totalContractExpenses + $totalSpreadsheets)), 2);

        return [
            Stat::make('Ganancias', '₡ '. $totalDifference)
            ->description('Ingresos - (Gastos cubiertos + Contratos + Planillas)')
            ->descriptionIcon('heroicon-o-arrows-right-left')
            ->chart($monthlyDifferences)
            ->color('info'),
        ];
    }

}
