<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Income;
use App\Models\Expense;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Carbon\Carbon;

class ProjectProfitabilityWidget extends BaseWidget
{
    public ?Project $record;

    protected static ?string $pollingInterval = '5s';

    protected int | string | array $columnSpan = 6;

    public function getColumns(): int 
    {
        return 1;
    }

    protected ?string $heading = 'Análisis de Rentabilidad';
    protected ?string $description = 'Margen de ganancia, ROI y tendencia de rentabilidad del proyecto';

    protected function getStats(): array
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);

        // Calculate total income
        $totalIncome = Income::where('project_id', $this->record->id)->sum('total_deposited');

        // Calculate total expenses (excluding credit notes)
        $totalExpensesPaid = Expense::where('project_id', $this->record->id)
            ->where('type', 'paid')
            ->where('document_type', '!=', 'nota_credito')
            ->sum('amount');

        $totalContractExpenses = DB::table('contract_expenses')
            ->join('contracts', 'contract_expenses.contract_id', '=', 'contracts.id')
            ->join('projects', 'contracts.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->sum('contract_expenses.total_deposited');

        $totalSpreadsheetExpenses = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->sum('payments.salary');

        $totalExpenses = $totalExpensesPaid + $totalContractExpenses + $totalSpreadsheetExpenses;

        // Calculate current profit margin
        $currentProfitMargin = $totalIncome > 0 ? (($totalIncome - $totalExpenses) / $totalIncome) * 100 : 0;

        // Calculate monthly profit margins for chart
        $monthlyProfitMargins = [];
        
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

        // Get monthly expenses (excluding credit notes)
        $monthlyExpenses = Expense::where('project_id', $this->record->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', 'paid')
            ->where('document_type', '!=', 'nota_credito')
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

        // Calculate profit margins for each month
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $key = $currentDate->format('Y-m');
            
            $income = $monthlyIncomes[$key] ?? 0;
            $expenses = ($monthlyExpenses[$key] ?? 0) + 
                       ($monthlyContractExpenses[$key] ?? 0) + 
                       ($monthlySpreadsheetExpenses[$key] ?? 0);
            
            $profitMargin = $income > 0 ? (($income - $expenses) / $income) * 100 : 0;
            $monthlyProfitMargins[$key] = round($profitMargin, 1);
            
            $currentDate->addMonth();
        }

        // Calculate average profit margin
        $averageProfitMargin = count($monthlyProfitMargins) > 0 
            ? round(array_sum($monthlyProfitMargins) / count($monthlyProfitMargins), 1) 
            : 0;

        return [
            Stat::make('Margen de Ganancia', number_format($currentProfitMargin, 1) . '%')
            ->description('Promedio últimos 6 meses: ' . $averageProfitMargin . '%')
            ->descriptionIcon('heroicon-o-chart-bar')
            ->chart($monthlyProfitMargins)
            ->color($currentProfitMargin >= 0 ? 'success' : 'danger'),
        ];
    }
}
