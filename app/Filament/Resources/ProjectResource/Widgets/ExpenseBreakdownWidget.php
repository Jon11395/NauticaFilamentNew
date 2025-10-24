<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Expense;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Carbon\Carbon;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class ExpenseBreakdownWidget extends BaseWidget
{
    use HasWidgetShield;
    
    public ?Project $record;

    protected static ?string $pollingInterval = '5s';

    protected int | string | array $columnSpan = 6;

    public function getColumns(): int 
    {
        return 1;
    }

    protected ?string $heading = 'Análisis Detallado de Gastos';
    protected ?string $description = 'Desglose por categorías: gastos directos, contratos y planillas';

    protected function getStats(): array
    {
        // Calculate total expenses by category
        $totalExpensesPaid = Expense::where('project_id', $this->record->id)
            ->where('type', 'paid')
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

        // Calculate percentages
        $expenseBreakdown = [];
        if ($totalExpenses > 0) {
            $expenseBreakdown['Gastos Regulares'] = round(($totalExpensesPaid / $totalExpenses) * 100, 1);
            $expenseBreakdown['Contratos'] = round(($totalContractExpenses / $totalExpenses) * 100, 1);
            $expenseBreakdown['Planillas'] = round(($totalSpreadsheetExpenses / $totalExpenses) * 100, 1);
        } else {
            $expenseBreakdown['Gastos Regulares'] = 0;
            $expenseBreakdown['Contratos'] = 0;
            $expenseBreakdown['Planillas'] = 0;
        }

        // Find the largest expense category
        $largestCategory = array_keys($expenseBreakdown, max($expenseBreakdown))[0];
        $largestPercentage = max($expenseBreakdown);

        // Create chart data (simplified for display)
        $chartData = [
            'Gastos Regulares' => $expenseBreakdown['Gastos Regulares'],
            'Contratos' => $expenseBreakdown['Contratos'],
            'Planillas' => $expenseBreakdown['Planillas'],
        ];

        return [
            Stat::make('Mayor Gasto', $largestCategory)
            ->description($largestPercentage . '% del total de gastos')
            ->descriptionIcon('heroicon-o-chart-bar')
            ->chart($chartData)
            ->color('warning'),
        ];
    }
}
