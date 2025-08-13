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
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                PhoneColumn::make('phone')
                    ->label('Teléfono')
                    ->displayFormat(PhoneInputNumberType::NATIONAL)
                    ->formatStateUsing(fn ($state) => $state ?? 'Sin teléfono'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('País')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('state.name')
                    ->label('Estado')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Ciudad')
                    ->numeric()
                    ->sortable(),
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
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListProviders::route('/'),
            //'create' => Pages\CreateProvider::route('/create'),
            //'edit' => Pages\EditProvider::route('/{record}/edit'),
        ];
    }
}
