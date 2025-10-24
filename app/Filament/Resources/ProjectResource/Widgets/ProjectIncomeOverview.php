<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Income;
use App\Models\Project;
use App\Filament\Resources\ProjectResource;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class ProjectIncomeOverview extends BaseWidget
{

    use InteractsWithPageTable;

    protected static ?string $pollingInterval = '5s';

    public ?Project $record;

    
    protected int | string | array $columnSpan = 12;

    public function getColumns(): int 
    {
        return 1;
    }

    protected ?string $heading = 'Análisis de Ingresos';
    protected ?string $description = 'Ingresos totales, tendencia mensual y comparación con períodos anteriores';
    


    protected function getStats(): array
    {

        $totalIncomeDeposited = number_format(Income::where('project_id', $this->record->id)->sum('total_deposited'), 2);
        $numberofincomes = Income::where('project_id', $this->record->id)->count();



        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(6);
        
        $incomeAmounts = Income::where('project_id', $this->record->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->select(DB::raw('SUM(total_deposited) as total'), DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'))
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
            ->orderBy(DB::raw('YEAR(date)'), 'ASC')
            ->orderBy(DB::raw('MONTH(date)'), 'ASC')
            ->get()
            ->mapWithKeys(function ($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => (float) $item->total];
            })
            ->toArray();


   
        if($numberofincomes == 1){
            $label = 'ingreso';
        }else{
            $label = 'ingresos';
        }

        return [
            Stat::make('Ingresos', '₡ '.$totalIncomeDeposited)
            ->description($numberofincomes.' '.$label)
            ->descriptionIcon('heroicon-m-arrow-trending-up')
            ->chart($incomeAmounts)
            ->color('success'),
        ];
    }

    protected function getTablePage(): string {
        return Income::class;
    }


}
