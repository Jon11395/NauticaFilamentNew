<?php

namespace App\Filament\Widgets;

use App\Models\Income;
use App\Models\Expense;
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
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class AllStatOverview extends BaseWidget
{
    use HasWidgetShield;
    protected static ?int $sort = 1;


    protected function getStats(): array
    {
        $totalOffers = Project::sum('offer_amount');
        $totalIncome = Income::sum('total_deposited');
        $totalExpenses = Expense::sum('amount');
        $totalDifference = $totalIncome - $totalExpenses;

        $totalOffersFormatted = number_format($totalOffers, 2);
        $totalIncomeDepositedFormatted = number_format($totalIncome, 2);
        $totalExpensesFormatted = number_format($totalExpenses, 2);
        $totalDifferenceFormatted = number_format($totalDifference, 2);

        $numberofincomes = Income::count();
        $labelincome = ($numberofincomes == 1) ? 'ingreso' : 'ingresos';

        $numberofexpenses = Expense::count();
        $labelexpenses = ($numberofexpenses == 1) ? 'gasto' : 'gastos';

        return [
            /*
            Stat::make('Ofertas', '₡ '.$totalOffersFormatted)
                ->description('Ofertas')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('warning'),

            Stat::make('Ingresos', '₡ '.$totalIncomeDepositedFormatted)
                ->description($numberofincomes.' '.$labelincome)
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Gastos', '₡ '.$totalExpensesFormatted)
                ->description($numberofexpenses.' '.$labelexpenses)
                ->descriptionIcon('heroicon-c-arrow-trending-down')
                ->color('danger'),

            Stat::make('Ganancias', '₡ '.$totalDifferenceFormatted)
                ->description('Ingresos - Gastos')
                ->descriptionIcon('heroicon-o-arrows-right-left')
                ->color('info'),

            */
        ];
    }
}
