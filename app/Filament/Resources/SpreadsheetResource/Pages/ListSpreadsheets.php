<?php

namespace App\Filament\Resources\SpreadsheetResource\Pages;

use App\Filament\Resources\SpreadsheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Guava\FilamentNestedResources\Concerns\NestedPage;

class ListSpreadsheets extends ListRecords
{
    use NestedPage;
    
    protected static string $resource = SpreadsheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
