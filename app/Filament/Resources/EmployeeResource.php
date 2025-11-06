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
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\Tables\PhoneColumn;
use Ysfkaya\FilamentPhoneInput\Infolists\PhoneEntry;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Altwaireb\World\Models\State;
use Altwaireb\World\Models\City;
use Illuminate\Support\Collection;



class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    //ordena los tabs del sidebar
    protected static ?int $navigationSort = 3;

    //cambia el icono de del tab del sidebar: https://blade-ui-kit.com/blade-icons?set=1#search
    protected static ?string $navigationIcon = 'heroicon-o-users';

    //agrega un label al tab en el sidebar
    protected static ?string $navigationLabel = 'Empleados';

    //agrega un label al breadcrumb
    protected static ?string $breadcrumb = "Empleados";

    protected static ?string $navigationGroup = 'Proyectos';



    public static function form(Form $form): Form
    {

        return $form
            ->schema([

                Section::make('Información personal')
                ->columns([
                    'sm' => 3,
                    'xl' => 3,
                    '2xl' => 3,
                ])
                ->schema([
                    Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                    PhoneInput::make('phone')
                    ->label('Teléfono')
                    ->initialCountry('cr')
                    ->strictMode(),
                    Forms\Components\TextInput::make('identification')
                    ->label('Cédula')
                    ->regex('/^[0-9\-]+$/')
                    ->validationMessages([
                        'regex' => 'La identificación solo puede contener números y guiones (-).'
                    ])
                    ->placeholder('Ej: 123456789 o 123-456789')
                    ->maxLength(50)
                    ->formatStateUsing(fn ($state) => $state ? '****' . substr($state, -4) : '')
                    ->dehydrateStateUsing(fn ($state) => $state)
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('view_id')
                            ->icon('heroicon-m-eye')
                            ->tooltip('Ver identificación completa')
                            ->modalHeading('Identificación completa')
                            ->modalContent(fn ($get, $record) => view('filament.components.identification-modal', [
                                'identification' => $record?->identification ?? $get('identification')
                            ]))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                            ->visible(fn ($get) => !empty($get('identification')))
                    ),
                    Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                    Forms\Components\TextInput::make('function')
                    ->label('Función')
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
                ]),

                Section::make('Dirección')
                ->columns([
                    'sm' => 3,
                    'xl' => 3,
                    '2xl' => 3,
                ])
                ->schema([
                    Forms\Components\Select::make('country_id')
                        ->label('País')
                        ->relationship(name:'country', titleAttribute:'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('state_id', null);
                            $set('city_id', null);
                        }) 
                        ->required(),
                    Forms\Components\Select::make('state_id')
                        ->options(fn (Get $get): Collection => State::query()
                            ->where('country_id', $get('country_id'))
                            ->pluck('name', 'id')
                        )
                        ->label('Estado o Provincia')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('city_id', null);
                        }) 
                        ->required(),
                    Forms\Components\Select::make('city_id')
                        ->options(fn (Get $get): Collection => City::query()
                            ->where('state_id', $get('state_id'))
                            ->pluck('name', 'id')
                        )
                        ->label('Ciudad')
                        ->searchable()
                        ->preload()
                        ->required(),
                    
                ]),

                Section::make('Detalles de nómina')
                ->columns([
                    'sm' => 3,
                    'xl' => 3,
                    '2xl' => 3,
                ])
                ->schema([
                    Forms\Components\TextInput::make('account_number')
                    ->label('Cuenta Bancaria')
                    ->placeholder('Ej: CR123456789123456789'),
                    Forms\Components\TextInput::make('hourly_salary')
                    ->label('Salario por hora')
                    ->prefix('₡')
                    ->default(0)
                    ->required()
                    ->numeric()
                    ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                ]),

                                    
            ]);

            
        

    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Empleados')
            ->description('Lista de empleados')
            ->columns([
                Split::make([
                    Tables\Columns\TextColumn::make('name')
                        ->label('Nombre')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Bold),
                    Stack::make([
                        Tables\Columns\TextColumn::make('function')
                            ->label('Función')
                            ->searchable()
                            ->sortable()
                            ->icon('heroicon-m-briefcase')
                            ->formatStateUsing(fn ($state) => $state ?? 'Sin función'),
                        Tables\Columns\TextColumn::make('identification')
                            ->label('Identificación')
                            ->searchable()
                            ->sortable()
                            ->icon('heroicon-m-identification')
                            ->formatStateUsing(fn ($state) => $state ? '****' . substr($state, -4) : 'Sin identificación'),
                        PhoneColumn::make('phone')
                            ->label('Teléfono')
                            ->displayFormat(PhoneInputNumberType::INTERNATIONAL)
                            ->copyable()
                            ->formatStateUsing(fn ($state) => $state ? preg_replace('/^(\+\d{3})(\d+)/', '$1 $2', $state) : 'Sin teléfono')
                            ->icon('heroicon-m-phone')
                            ->sortable(),
                        Tables\Columns\TextColumn::make('email')
                            ->label('Email')
                            ->searchable()
                            ->sortable()
                            ->copyable()
                            ->icon('heroicon-m-envelope')
                            ->formatStateUsing(fn ($state) => $state ?? 'Sin email'),
                    ]),
                    Stack::make([
                        Tables\Columns\TextColumn::make('city.name')
                            ->label('Ciudad')
                            ->searchable()
                            ->sortable()
                            ->icon('heroicon-m-building-office-2'),
                        Tables\Columns\TextColumn::make('state.name')
                            ->label('Estado/Provincia')
                            ->searchable()
                            ->sortable()
                            ->icon('heroicon-m-map-pin'),
                        Tables\Columns\TextColumn::make('country.name')
                            ->label('País')
                            ->searchable()
                            ->sortable()
                            ->icon('heroicon-m-flag'),
                    ]),
                    Stack::make([
                        Tables\Columns\TextColumn::make('hourly_salary')
                            ->label('Salario por hora')
                            ->money('CRC')
                            ->sortable()
                            ->icon('heroicon-m-banknotes')
                            ->formatStateUsing(fn ($state) => $state ? '₡' . number_format($state, 2) . ' x hora' : 'Sin salario'),
                        Tables\Columns\TextColumn::make('account_number')
                            ->label('Cuenta Bancaria')
                            ->searchable()
                            ->sortable()
                            ->copyable()
                            ->icon('heroicon-m-credit-card')
                            ->formatStateUsing(fn ($state) => $state ?? 'Sin cuenta'),
                        
                    ]),
                    Stack::make([
                        Tables\Columns\ToggleColumn::make('active')
                            ->label('Estado')
                            ->sortable()
                            ->onIcon('heroicon-m-user')
                            ->offIcon('heroicon-m-user')
                            ->onColor('success')
                            ->offColor('danger'),
                        
                    ]),

                    
                ]),
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
            ])
            ->recordUrl(fn () => null)
            ->recordAction(null);
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
