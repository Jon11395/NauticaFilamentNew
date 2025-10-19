<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Store the original identification value
        if (isset($data['identification'])) {
            $data['identification_original'] = $data['identification'];
        }
        return $data;
    }

    //redirige despues de guardar a la anterior pagina
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    //acciones dentro de esta pagina, oculta el boton de eliminar
    protected function getHeaderActions(): array
    {
        return [
            //Actions\DeleteAction::make(),
        ];
    }
}
