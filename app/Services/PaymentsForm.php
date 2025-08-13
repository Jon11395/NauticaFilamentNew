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

}