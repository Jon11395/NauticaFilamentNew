<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Expense;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectExpenseTotalOverview extends BaseWidget
{
    public ?Project $record;

    protected static ?string $pollingInterval = '5s';

    protected int | string | array $columnSpan = 12;

    public function getColumns(): int 
    {
        return 1;
    }

    protected ?string $heading = 'Resumen Total de Gastos';
    protected ?string $description = 'Consolidado de todos los gastos: directos, contratos y planillas';


    protected function getStats(): array
    {

        $totalExpenses = Expense::where('project_id', $this->record->id)->where('type', 'paid')->sum('amount');
        $numberofexpenses = Expense::where('project_id', $this->record->id)->where('type', 'paid')->count();


        $totalSpreadsheetPaid = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
                ->where('projects.id', $this->record->id)
                ->sum('payments.salary');
        $numberofspreadsheetpayments = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)->count();


        $totalContractExpenses = DB::table('contract_expenses')
            ->join('contracts', 'contract_expenses.contract_id', '=', 'contracts.id')
            ->join('projects', 'contracts.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->sum('contract_expenses.total_deposited');
        $numberofcontractexpenses = DB::table('contract_expenses')
            ->join('contracts', 'contract_expenses.contract_id', '=', 'contracts.id')
            ->join('projects', 'contracts.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)->count();


        //dump($totalSpreadsheetPaid);
        $total= number_format(($totalExpenses + $totalSpreadsheetPaid + $totalContractExpenses), 2);
        $totalnumbers= $numberofexpenses + $numberofspreadsheetpayments + $numberofcontractexpenses;

        return [


            Stat::make('Total de gastos (no incluye cuentas por pagar)', '₡ '.$total)
            ->description($totalnumbers.' ' . $this->pluralize($totalnumbers, 'gasto', 'gastos'))
            ->descriptionIcon('heroicon-c-arrow-trending-down')
            ->chart([7, 2, 10, 3, 15, 4, 17])
            ->color('danger'),
        ];
    }

    private function pluralize(int $count, string $singular, string $plural): string
    {
        return ($count === 1) ? $singular : $plural;
    }


}
