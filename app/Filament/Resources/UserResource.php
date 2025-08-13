<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;


class UserResource extends Resource
{


    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Usuarios';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?string $breadcrumb = "Usuarios";
    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('roles')
                    ->label('Roles')
                    ->multiple()
                    ->preload() // loads all roles to prevent AJAX delay
                    ->relationship('roles', 'name') // works if User model has roles() relationship
                    ->searchable(),
                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->maxLength(255)
                    ->required(fn (string $context): bool => $context === 'create')  // solo requerido en creación
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                    ->dehydrated(fn ($state) => filled($state)) // solo se guarda si hay valor
                    ->visible(fn (string $context): bool => $context === 'create'), 
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Usuario')
            ->description('Lista de usuarios del sistema')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        default => 'success',
                    })
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->id !== 1 && $record->email !== 'admin@admin.com'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->id !== 1 && $record->email !== 'admin@admin.com'),
                Action::make('resetPassword')
                    ->label('Restaurar contraseña')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-path') 
                    ->requiresConfirmation()
                    ->tooltip("Contraseña por defecto: '12345'")
                    ->visible(fn ($record) => auth()->user()->hasAnyRole(['admin', 'super_admin']) 
                        && $record->id !== 1 && $record->email !== 'admin@admin.com')
                    ->action(function ($record, $data, $livewire) {
                        $defaultPassword = '12345'; // default password

                        $record->password = Hash::make($defaultPassword);
                        $record->save();

                        Notification::make()
                        ->success()
                        ->title("Contraseña restaurada para el usuario {$record->name}")
                        ->send();
                    }),
                ActivityLogTimelineTableAction::make('Activities')
                    ->label('Actividad')
                    ->color('info')
                    ->limit(15),
                
            ])
            ->bulkActions([

            ]);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            //'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
