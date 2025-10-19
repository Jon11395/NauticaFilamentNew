<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Provider;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Altwaireb\World\Models\State;
use Altwaireb\World\Models\City;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Section;
use App\Filament\Resources\ProviderResource\Pages;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\Tables\PhoneColumn;
use Ysfkaya\FilamentPhoneInput\Infolists\PhoneEntry;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;


class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationGroup = 'Proyectos';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Proveedores';
    protected static ?string $breadcrumb = "Proveedores";




    public static function infolists(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns([
                Infolists\Components\TextEntry::make('name'),
                Tables\Columns\TextColumn::make('email'),
                PhoneEntry::make('phone')->displayFormat(PhoneInputNumberType::NATIONAL),
            ]);
    }

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
                            ->required()
                            ->maxLength(255),
                        PhoneInput::make('phone')
                            ->label('Teléfono')
                            ->initialCountry('cr')
                            ->strictMode(),
                        Forms\Components\TextInput::make('email')
                            ->required()
                            ->email()
                            ->maxLength(255),
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
       
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Proveedores')
            ->description('Lista de proveedores')
            ->columns([
                Split::make([
                    Tables\Columns\TextColumn::make('name')
                        ->label('Nombre')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Bold),
                    Stack::make([
                        PhoneColumn::make('phone')
                            ->label('Teléfono')
                            ->copyable()
                            ->displayFormat(PhoneInputNumberType::NATIONAL)
                            ->formatStateUsing(fn ($state) => $state ? preg_replace('/^(\+\d{3})(\d+)/', '$1 $2', $state) : 'Sin teléfono')
                            ->icon('heroicon-m-phone')
                            ->sortable(),
                        Tables\Columns\TextColumn::make('email')
                            ->label('Email')
                            ->searchable()
                            ->copyable()
                            ->sortable()
                            ->icon('heroicon-m-envelope'),
                    ]),
                    Stack::make([
                        Tables\Columns\TextColumn::make('city.name')
                            ->label('Ciudad')
                            ->numeric()
                            ->sortable()
                            ->icon('heroicon-m-building-office-2'),
                        Tables\Columns\TextColumn::make('state.name')
                            ->label('Estado')
                            ->numeric()
                            ->sortable()
                            ->icon('heroicon-m-map-pin'),
                        Tables\Columns\TextColumn::make('country.name')
                            ->label('País')
                            ->numeric()
                            ->sortable()
                            ->icon('heroicon-m-flag'),
                        
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
            'index' => Pages\ListProviders::route('/'),
            //'create' => Pages\CreateProvider::route('/create'),
            //'edit' => Pages\EditProvider::route('/{record}/edit'),
        ];
    }
}
