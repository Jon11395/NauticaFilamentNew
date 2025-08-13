<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Resources\ProjectResource;
use Guava\FilamentNestedResources\Concerns\NestedPage;
use App\Filament\Resources\ProjectResource\Widgets\ProjectIncomeOverview;
use App\Filament\Resources\ProjectResource\Widgets\ProjectExpenseOverview;
use App\Filament\Resources\ProjectResource\Widgets\ProjectDifferenceOverview;
use App\Filament\Resources\ProjectResource\Widgets\ProjectSpreadsheetOverview;
use App\Filament\Resources\ProjectResource\Widgets\ProjectExpenseToPayOverview;
use App\Filament\Resources\ProjectResource\Widgets\ProjectContractExpensesOverview;


class ViewProject extends ViewRecord
{

    use NestedPage;

    protected static string $resource = ProjectResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('Proyecto - '. $this->record->name);
    }


    public static function getNavigationLabel(): string
    {
        return 'Resumen';
    }
    
    protected static string $view = 'filament.resources.projects.pages.view-project';


    public function getFooterWidgetsColumns(): int
    {
        return 12;
    }

    protected function getFooterWidgets(): array {
        return [
            ProjectIncomeOverview::class,
            ProjectExpenseOverview::class,
            ProjectContractExpensesOverview::class,
            ProjectExpenseToPayOverview::class,
            ProjectSpreadsheetOverview::class,
            ProjectDifferenceOverview::class,
            
        ];
    }

    /*public function getFooterWidgetsColumns(): int | array
    {
        return 12;
    } */

}
