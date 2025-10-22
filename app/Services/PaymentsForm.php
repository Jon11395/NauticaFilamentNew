<?php

namespace App\Services;

use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;


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