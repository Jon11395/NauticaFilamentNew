<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Models\User;
use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Resources\ProjectResource;
use Filament\Notifications\Notification;
use Guava\FilamentNestedResources\Concerns\NestedPage;


class CreateProject extends CreateRecord
{
    use NestedPage;

    protected static string $resource = ProjectResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('Crear proyecto');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::user()->id;
        
        //toma los datos del form
        $name = $data['name'];

        //crea una notificaion para cada usuario
        $recipient = User::all();

        //para que funcionen la notificaciones hay leventar el queue de notificaciones con sail php artisan queue:work
        Notification::make()
            ->title('Proyecto creado exitosamente')
            ->body(Auth::user()->name . ' ha creado el proyecto: ' . $name )
            ->info()
            ->color('info') 
            ->sendToDatabase($recipient);


        return $data;
    }
}
