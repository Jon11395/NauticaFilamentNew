<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Resources\EmployeeResource;



class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
    

    //agrega un titulo a la pagina
    public function getTitle(): string | Htmlable
    {
        return __('Crear Empleado');
    }

    //redirige despues de guardar a la anterior pagina
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


    //crea una notificacion de base de datos despues de guardar
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::user()->id;
        
        //toma los datos del form
        $name = $data['name'];

        //crea una notificaion para cada usuario
        $recipient = User::all();

        //para que funcionen la notificaciones hay leventar el queue de notificaciones con sail php artisan queue:work
        Notification::make()
            ->title('Empleado creado exitosamente')
            ->body(Auth::user()->name . ' ha creado el empleado: ' . $name )
            ->info()
            ->color('info') 
            ->sendToDatabase($recipient);


        return $data;
    }
}
