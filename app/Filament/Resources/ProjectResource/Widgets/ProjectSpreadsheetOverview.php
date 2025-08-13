<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Income;
use App\Models\Project;
use App\Filament\Resources\ProjectResource;
use App\Models\Spreadsheet;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class ProjectSpreadsheetOverview extends BaseWidget
{

    use InteractsWithPageTable;

    protected static ?string $pollingInterval = '5s';

    public ?Project $record;

    protected int | string | array $columnSpan = 6;

    public function getColumns(): int 
    {
        return 6;
    }


    protected function getStats(): array
    {

        $totalSpreadsheetPaid = DB::table('payments')
        ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
        ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)
            ->sum('payments.salary');

        $numberofpayments = DB::table('payments')
            ->join('spreadsheets', 'payments.spreadsheet_id', '=', 'spreadsheets.id')
            ->join('projects', 'spreadsheets.project_id', '=', 'projects.id')
            ->where('projects.id', $this->record->id)->count();



        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);
        
        $PaymentsCountsByMonth = DB::table('payments')
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
                // Format the key as "year-month" and the value as the count of records
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => $item->count];
            })
            ->toArray();

   
        if($numberofpayments == 1){
            $label = 'pago';
        }else{
            $label = 'pagos';
        }

        return [
            Stat::make('Planillas', '₡ '. number_format($totalSpreadsheetPaid, 2))
            ->description($numberofpayments.' '.$label)
            ->descriptionIcon('heroicon-c-arrow-trending-down')
            ->chart($PaymentsCountsByMonth)
            ->color('gray'),
        ];
    }

    protected function getTablePage(): string {
        return Spreadsheet::class;
    }


}
