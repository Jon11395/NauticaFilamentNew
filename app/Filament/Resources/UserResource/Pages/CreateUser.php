<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Crear usuario');
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::user()->id;

        $recipient = User::all();
        

        //para que funcionen la notificaciones hay leventar el queue de notificaciones con sail php artisan queue:work
        Notification::make()
            ->title('Usuario creado exitosamente')
            ->body(Auth::user()->name . ' ha creado un nuevo usuario:')
            ->info()
            ->sendToDatabase($recipient);


        return $data;
    }
    

  
}
