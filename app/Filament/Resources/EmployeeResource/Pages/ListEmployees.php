<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Resources\Components\Tab;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    //agrega un nombre al boton de guardar
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('Crear empleado'),
        ];
    }

    //agrega un titulo a la pagina
    public function getTitle(): string | Htmlable
    {
        return __('Empleados');
    }

    //Hace tabs en las tablas y los filtra
    public function getTabs(): array
    {
        $tabs = [];
    
    
        $tabs[] = Tab::make('Todos')
            // Add badge to the tab
            ->badge(Employee::count())
            ->badgeColor('info')
            ->icon('heroicon-o-list-bullet');
            
            // No need to modify the query as we want to show all tasks
    
        $tabs[] = Tab::make('Activos') 
            // Add badge to the tab
            ->badge(Employee::where('active', true)->count())
            ->icon('heroicon-s-check-circle')
            ->badgeColor('success')
            // Modify the query only to show completed tasks
            ->modifyQueryUsing(function ($query) {
                return $query->where('active', true);
            });
    
        $tabs[] = Tab::make('Inactivos')
            // Add badge to the tab
            ->badge(Employee::where('active', false)->count())
            ->icon('heroicon-c-x-circle')
            ->badgeColor('danger')
            // Modify the query only to show incomplete tasks
            ->modifyQueryUsing(function ($query) {
                return $query->where('active', false);
            });
    
        return $tabs;
    }
    

}
