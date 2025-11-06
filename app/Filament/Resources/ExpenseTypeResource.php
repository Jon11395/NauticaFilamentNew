<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseTypeResource\Pages;
use App\Filament\Resources\ExpenseTypeResource\RelationManagers;
use App\Models\ExpenseType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;

class ExpenseTypeResource extends Resource
{
    protected static ?string $model = ExpenseType::class;

    protected static ?string $navigationGroup = 'Proyectos';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Tipos de gastos';
    protected static ?string $breadcrumb = "Tipos de gastos";
    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Tipos de gastos')
            ->description('Lista de tipos de gastos')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
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
            'index' => Pages\ListExpenseTypes::route('/'),
            //'create' => Pages\CreateExpenseType::route('/create'),
            //'edit' => Pages\EditExpenseType::route('/{record}/edit'),
        ];
    }
}
