<?php

namespace App\Services;

use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Altwaireb\World\Models\State;
use Altwaireb\World\Models\City;


final class PaymentsForm{

    public static function schema($id): array{
        return [
            Forms\Components\Grid::make(3)
                ->schema([
                    
                    Forms\Components\TextInput::make('salary')
                        ->label('Salario')
                        ->prefix('₡')
                        ->required()
                        ->numeric()
                        ->default(0)
                        ->live(true)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            self::updateTotals($get, $set);
                        })
                        ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                    Forms\Components\TextInput::make('additionals')
                        ->label('Adicionales')
                        ->prefix('₡')
                        ->required()
                        ->numeric()
                        ->default(0)
                        ->live(true)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            self::updateTotals($get, $set);
                        })
                        ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                    Forms\Components\TextInput::make('rebates')
                        ->label('Rebajas')
                        ->prefix('₡')
                        ->required()
                        ->numeric()
                        ->default(0)
                        ->live(true)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            self::updateTotals($get, $set);
                        })
                        ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                    Forms\Components\TextInput::make('ccss')
                        ->label('CCSS')
                        ->prefix('₡')
                        ->required()
                        ->numeric()
                        ->default(0)
                        ->live(true)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            self::updateTotals($get, $set);
                        })
                        ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                    Forms\Components\TextInput::make('deposited')
                        ->label('Total depositado')
                        ->readonly()
                        ->default(0)
                        ->prefix('₡')
                        ->live(true)
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            self::updateTotals($get, $set);
                        })
                        ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                    Forms\Components\Select::make('employee_id')
                        ->label('Empleado')
                        //->relationship(name:'employee', titleAttribute:'name')
                        ->relationship('employee', 'name', fn (Builder $query) => $query->where('active', true))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\Section::make('Información personal')
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
                                    Forms\Components\Hidden::make('active')
                                        ->default(1),
                                ])
                                ->columns([
                                    'sm' => 3,
                                    'xl' => 3,
                                    '2xl' => 3,
                                ]),
                            Forms\Components\Section::make('Dirección')
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
                                ])
                                ->columns([
                                    'sm' => 3,
                                    'xl' => 3,
                                    '2xl' => 3,
                                ]),
                            Forms\Components\Section::make('Detalles de nómina')
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
                                ])
                                ->columns([
                                    'sm' => 3,
                                    'xl' => 3,
                                    '2xl' => 3,
                                ]),
                        ]),
                    Forms\Components\TextInput::make('description')
                        ->label('Descripción')
                        ->maxLength(255),
                    Forms\Components\Hidden::make('spreadsheet_id')
                        ->label('spreadsheet_id')
                        ->required()
                        ->default($id)

                ])
            
        ];
    }

    public static function updateTotals(Get $get, Set $set): void
    {
        $salary = (float) ($get('salary') ?? 0);
        $additionals = (float) ($get('additionals') ?? 0);
        $rebates = (float) ($get('rebates') ?? 0);
        $ccss = (float) ($get('ccss') ?? 0);

        $total = ($salary + $additionals) - ($rebates + $ccss);
        
        $set('deposited', $total);
    }

}