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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->dateTime()
                    ->sortable(),
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
