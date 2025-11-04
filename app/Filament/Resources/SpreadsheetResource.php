<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SpreadsheetResource\Pages;
use App\Filament\Resources\SpreadsheetResource\RelationManagers;
use App\Models\Spreadsheet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Guava\FilamentNestedResources\Concerns\NestedResource;
use Guava\FilamentNestedResources\Ancestor;

class SpreadsheetResource extends Resource
{
    use NestedResource;

    protected static ?string $model = Spreadsheet::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DateTimePicker::make('date')
                    ->required(),
                Forms\Components\TextInput::make('period')
                    ->label('Período')
                    ->placeholder('07/10/2025 - 21/10/2025')
                    ->disabled(),
                Forms\Components\FileUpload::make('attachment')
                    ->label('Archivo Adjunto')
                    ->acceptedFileTypes(['application/pdf'])
                    ->directory('spreadsheets')
                    ->visibility('private'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('period')
                    ->label('Período')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attachment')
                    ->label('Archivo')
                    ->formatStateUsing(fn ($state) => $state ? '📎 PDF' : 'Sin archivo')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListSpreadsheets::route('/'),
            'create' => Pages\CreateSpreadsheet::route('/create'),
            'edit' => Pages\EditSpreadsheet::route('/{record}/edit'),
        ];
    }

    public static function getAncestor() : ?Ancestor
    {
        // Configure the ancestor (parent) relationship here
        return Ancestor::make(
            'spreadsheets', // Relationship name
            'project', // Inverse relationship name
        );
    }
}
