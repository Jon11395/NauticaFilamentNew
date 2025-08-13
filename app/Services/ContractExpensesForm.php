<?php

namespace App\Services;


use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\FileUpload;


final class ContractExpensesForm{

    public static function schema($id): array{
        return [
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('voucher')
                        ->label('Comprobante')
                        ->required()
                        ->numeric(),
                    Forms\Components\DatePicker::make('date')
                        ->label('Fecha')
                        ->required()
                        ->default(now()),
                    Forms\Components\TextInput::make('concept')
                        ->label('Concepto')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('total_solicited')
                        ->label('Solicitado')
                        ->prefix('₡')
                        ->required()
                        ->numeric()
                        ->default(0)
                        ->live(true)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            self::updateTotals($get, $set);
                        })
                        ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                    Forms\Components\TextInput::make('retentions')
                        ->label('Retenciones')
                        ->prefix('₡')
                        ->required()
                        ->numeric()
                        ->default(0)
                        ->live(true)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            self::updateTotals($get, $set);
                        })
                        ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                    Forms\Components\TextInput::make('CCSS')
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
                    Forms\Components\TextInput::make('total_deposited')
                        ->label('Total depositado')
                        ->readonly()
                        ->default(0)
                        ->prefix('₡')
                        ->live(true)
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            self::updateTotals($get, $set);
                        })
                        ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                    Forms\Components\Hidden::make('contract_id')
                        ->label('contract_id')
                        ->required()
                        ->default($id),
                    Forms\Components\FileUpload::make('attachment')
                        ->disk('public') // use the public disk, which points to storage/app/public
                        ->directory('contract_expenses/attachments')
                        ->acceptedFileTypes(['application/pdf', 'image/*']) 
                        ->label('Archivo adjunto (PDF o imagen)')
                        ->maxSize(10240)
                        ->nullable()
                        //->multiple()
                        ->panelLayout('grid'),

                ])
            
        ];
    }

    public static function updateTotals(Get $get, Set $set): void
    {
        $total_solicited = $get('total_solicited');
        $CCSS = $get('CCSS');
        $retentions = $get('retentions');

        $total = ($total_solicited - ($CCSS + $retentions));
        
        $set('total_deposited', $total);
    }

}