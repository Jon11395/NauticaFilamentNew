<?php

namespace App\Filament\Resources\TemporalExpenseResource\Pages;

use App\Filament\Resources\TemporalExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTemporalExpense extends EditRecord
{
    protected static string $resource = TemporalExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['temporal'] = true;

        return $data;
    }
}
