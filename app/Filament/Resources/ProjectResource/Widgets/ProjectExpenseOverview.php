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

    protected int | string | array $columnSpan = 12;

    public function getColumns(): int 
    {
        return 2;
    }

    protected ?string $heading = 'Análisis Detallado de Gastos';
    protected ?string $description = 'Desglose completo por categorías con tendencias mensuales';




    protected function getStats(): array
    {


        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);

        //expenses
        $totalExpenses = number_format(Expense::where('project_id', $this->record->id)->where('type', 'paid')->sum('amount'), 2);
        $numberofexpenses = Expense::where('project_id', $this->record->id)->where('type', 'paid')->count();
        $expenseCountsPaid = Expense::where('project_id', $this->record->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', 'paid')
            ->select(DB::raw('COUNT(*) as count'), DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'))
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
            ->orderBy(DB::raw('YEAR(date)'), 'ASC')
            ->orderBy(DB::raw('MONTH(date)'), 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (int) $item->count];
            })
            ->toArray();

        //expenses from contracts
        $totalContractExpenses = DB::table('contract_expenses')
            ->join('contracts', 'contract_expenses.contract_id', '=', 'contracts.id')
            ->join('projects', 'contracts.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->sum('contract_expenses.total_deposited');

        $numberofcontractexpenses = DB::table('contract_expenses')
                            ->join('contracts', 'contract_expenses.contract_id', '=', 'contracts.id')
                            ->join('projects', 'contracts.project_id', '=', 'projects.id')
                            ->where('projects.id', $this->record->id)->count();

        $contractexpenseCountsByMonth = DB::table('contract_expenses')
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
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (int) $item->count];
            })
            ->toArray();


        //expenses from spreadsheets
        $totalSpreadsheetPaid = DB::table('payments')
        ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
        ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->sum('payments.salary');

        $numberofspreadsheetpayments = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)->count();

        $spreadsheetPaymentsCountsByMonth = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->whereBetween('spreadsheets.date', [$startDate, $endDate])
            ->select(DB::raw('COUNT(*) as count'), DB::raw('MONTH(spreadsheets.date) as month'), DB::raw('YEAR(spreadsheets.date) as year'))
            ->groupBy(DB::raw('YEAR(spreadsheets.date)'), DB::raw('MONTH(spreadsheets.date)'))
            ->orderBy(DB::raw('YEAR(spreadsheets.date)'), 'ASC')
            ->orderBy(DB::raw('MONTH(spreadsheets.date)'), 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (int) $item->count];
            })
            ->toArray();

   
        //expenses to pay
        $totalExpensesUnpaid = number_format(Expense::where('project_id', $this->record->id)->where('type', 'unpaid')->sum('amount'), 2);
        $numberofexpensesUnpaid = Expense::where('project_id', $this->record->id)->where('type', 'unpaid')->count();

        $expenseCountsUnpaid = Expense::where('project_id', $this->record->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', 'unpaid')
            ->select(DB::raw('COUNT(*) as count'), DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'))
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
            ->orderBy(DB::raw('YEAR(date)'), 'ASC')
            ->orderBy(DB::raw('MONTH(date)'), 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (int) $item->count];
            })
            ->toArray();

        

        return [
            Stat::make('Gastos cubiertos', '₡ ' . $totalExpenses)
            ->description($numberofexpenses . ' ' . $this->pluralize($numberofexpenses, 'gasto', 'gastos'))
            ->descriptionIcon('heroicon-c-arrow-trending-down')
            ->chart($expenseCountsPaid)
            ->color('danger'),

            Stat::make('Gastos por contratos', '₡ ' . number_format($totalContractExpenses, 2))
            ->description($numberofcontractexpenses . ' ' . $this->pluralize($numberofcontractexpenses, 'gasto', 'gastos'))
            ->descriptionIcon('heroicon-c-arrow-trending-down')
            ->chart($contractexpenseCountsByMonth)
            ->color('danger'),

            Stat::make('Gastos por planillas', '₡ ' . number_format($totalSpreadsheetPaid, 2))
            ->description($numberofspreadsheetpayments . ' ' . $this->pluralize($numberofspreadsheetpayments, 'pago de planilla', 'pagos de planillas'))
            ->descriptionIcon('heroicon-c-arrow-trending-down')
            ->chart($spreadsheetPaymentsCountsByMonth)
            ->color('danger'),

            Stat::make('Cuentas por pagar', '₡ '.$totalExpensesUnpaid)
            ->description($numberofexpensesUnpaid.' ' .'por pagar')
            ->descriptionIcon('heroicon-c-arrow-trending-down')
            ->chart($expenseCountsUnpaid)
            ->color('warning'),

        ];
    }

    private function pluralize(int $count, string $singular, string $plural): string
    {
        return ($count === 1) ? $singular : $plural;
    }

}
