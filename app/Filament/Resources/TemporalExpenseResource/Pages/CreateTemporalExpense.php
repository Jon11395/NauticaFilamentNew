<?php

namespace App\Filament\Resources\TemporalExpenseResource\Pages;

use App\Filament\Resources\TemporalExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTemporalExpense extends CreateRecord
{
    protected static string $resource = TemporalExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['temporal'] = true;

        return $data;
    }
}
