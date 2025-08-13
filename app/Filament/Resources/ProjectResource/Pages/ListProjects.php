<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Guava\FilamentNestedResources\Concerns\NestedPage;


class ListProjects extends ListRecords
{

    use NestedPage;

    protected static string $resource = ProjectResource::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Ver proyectos';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('Crear proyecto'),
        ];
    }

    public function getTitle(): string | Htmlable
    {
        return __('Proyectos');
    }

    public function getTabs(): array
    {
        $tabs = [];
    
    
        $tabs[] = Tab::make('Todos')
            ->badge(Project::count())
            ->badgeColor('info')
            ->icon('heroicon-o-list-bullet');
            

        $tabs[] = Tab::make('En progreso') 
            ->badge(Project::where('status', 'in_progress')->count())
            ->icon('heroicon-s-arrow-right-circle')
            ->badgeColor('warning')
            ->modifyQueryUsing(function ($query) {
                return $query->where('status', 'in_progress');
            });
    
        $tabs[] = Tab::make('Detenidos')
            ->badge(Project::where('status', 'stopped')->count())
            ->icon('heroicon-c-x-circle')
            ->badgeColor('danger')
            ->modifyQueryUsing(function ($query) {
                return $query->where('status', 'stopped');
            });

        $tabs[] = Tab::make('Terminados')
            ->badge(Project::where('status', 'finished')->count())
            ->icon('heroicon-s-check-circle')
            ->badgeColor('success')
            ->modifyQueryUsing(function ($query) {
                return $query->where('status', 'finished');
            });
    
        return $tabs;
    }
}
