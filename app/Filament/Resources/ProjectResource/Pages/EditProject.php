<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Guava\FilamentNestedResources\Concerns\NestedPage;
use Illuminate\Contracts\Support\Htmlable;

class EditProject extends EditRecord
{

    use NestedPage;

    protected static string $resource = ProjectResource::class;

    public static function getNavigationLabel(): string
    {
        return 'Editar proyecto';
    }

    public function getTitle(): string | Htmlable
    {
        return __('Editar proyecto - '. $this->record->name);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

}
