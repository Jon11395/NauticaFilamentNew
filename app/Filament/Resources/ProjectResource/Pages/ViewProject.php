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
use App\Filament\Resources\ProjectResource\Widgets\ProjectExpenseTotalOverview;
use App\Filament\Resources\ProjectResource\Widgets\ProjectProfitabilityWidget;
use App\Filament\Resources\ProjectResource\Widgets\PayrollSummaryWidget;
use App\Filament\Resources\ProjectResource\Widgets\EmployeeCountWidget;



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
            // === FINANCIAL PERFORMANCE SECTION ===
            ProjectIncomeOverview::class,
            ProjectExpenseOverview::class,
            ProjectProfitabilityWidget::class,
            ProjectDifferenceOverview::class,
            
            // === OPERATIONAL INSIGHTS SECTION ===
            PayrollSummaryWidget::class,
            EmployeeCountWidget::class,
            
        ];
    }



}
