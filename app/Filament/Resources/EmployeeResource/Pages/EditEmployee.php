<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

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
