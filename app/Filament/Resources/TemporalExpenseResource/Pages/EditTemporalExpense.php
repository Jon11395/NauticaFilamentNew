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
        // If both project_id and expense_type_id are provided, remove the temporal flag
        if (!empty($data['project_id']) && !empty($data['expense_type_id'])) {
            $data['temporal'] = false;
        } else {
            $data['temporal'] = true;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Ensure temporal flag is updated based on project_id and expense_type_id
        $record = $this->record;
        
        if (!empty($record->project_id) && !empty($record->expense_type_id)) {
            $record->temporal = false;
        } else {
            $record->temporal = true;
        }
        
        $record->save();
    }
}
