<?php

namespace App\Filament\Resources\ProviderResource\Pages;

use App\Filament\Resources\ProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListProviders extends ListRecords
{
    protected static string $resource = ProviderResource::class;


    public function getTitle(): string | Htmlable
    {
        return __('Proveedores');
    }


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('Crear proveedor'),
        ];
    }
}
