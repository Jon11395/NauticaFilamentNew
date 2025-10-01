<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Employee;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\EmployeeResource\Pages;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;



class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    //ordena los tabs del sidebar
    protected static ?int $navigationSort = 3;

    //cambia el icono de del tab del sidebar: https://blade-ui-kit.com/blade-icons?set=1#search
    //protected static ?string $navigationIcon = 'heroicon-m-users';

    //agrega un label al tab en el sidebar
    protected static ?string $navigationLabel = 'Empleados';

    //agrega un label al breadcrumb
    protected static ?string $breadcrumb = "Empleados";

    protected static ?string $navigationGroup = 'Proyectos';



    public static function form(Form $form): Form
    {

        return $form
            ->schema([

                
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('active')
                    ->label('Estado')
                    ->onIcon('heroicon-m-user')
                    ->offIcon('heroicon-m-user')
                    ->onColor('success')
                    ->offColor('danger')
                    ->inline(false)
                    ->default(1),
                    //oculta el boton para ciertos lugares
                    //->hiddenOn(Pages\CreateEmployee::class),
            ]);

            
        

    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Empleados')
            ->description('Lista de empleados')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('active')
                    ->label('Estado')
                    ->sortable()
                    ->onIcon('heroicon-m-user')
                    ->offIcon('heroicon-m-user')
                    ->onColor('success')
                    ->offColor('danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                ActivityLogTimelineTableAction::make('Activities')
                    ->label('Actividad')
                    ->color('info')
                    ->limit(15),
                
            ])
            ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
                    FilamentExportBulkAction::make('Exportar'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            //'create' => Pages\CreateEmployee::route('/create'),
            //si se comenta aparece un modal en vez de redirigir a otra pagina
            //'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
