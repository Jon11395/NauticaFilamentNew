<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TemporalExpenseResource\Pages;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Project;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TemporalExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';
    protected static ?string $navigationGroup = 'Proyectos';
    protected static ?string $navigationLabel = 'Gastos por asignar';
    protected static ?string $modelLabel = 'Gasto por asignar';
    protected static ?string $pluralModelLabel = 'Gastos por asignar';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del gasto')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('voucher')
                                    ->label('Comprobante')
                                    ->required()
                                    ->numeric(),
                                Forms\Components\DateTimePicker::make('date')
                                    ->label('Fecha')
                                    ->required(),
                            ]),
                        Forms\Components\Textarea::make('concept')
                            ->label('Concepto')
                            ->rows(3),
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Monto')
                                    ->prefix('₡')
                                    ->required()
                                    ->numeric()
                                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2),
                                Forms\Components\Select::make('type')
                                    ->label('Tipo')
                                    ->required()
                                    ->options([
                                        'paid' => 'Pagado',
                                        'unpaid' => 'No pagado',
                                    ]),
                                Forms\Components\Select::make('expense_type_id')
                                    ->label('Tipo de gasto')
                                    ->options(fn (): array => ExpenseType::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ]),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Asignaciones')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('provider_id')
                                    ->label('Proveedor')
                                    ->options(fn (): array => Provider::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\Select::make('project_id')
                                    ->label('Proyecto')
                                    ->relationship('project', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ]),
                    ])
                    ->columns(1),

                Forms\Components\Hidden::make('temporal')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('voucher')
                    ->label('Comprobante')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('concept')
                    ->label('Concepto')
                    ->limit(40)
                    ->tooltip(fn (Model $record) => $record->concept),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('CRC', locale: 'es_CR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Proveedor')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expenseType.name')
                    ->label('Tipo de gasto')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('has_attachment')
                    ->label('Adjunto')
                    ->state(fn (Expense $record): bool => filled($record->attachment))
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-x-mark'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project')
                    ->relationship('project', 'name')
                    ->label('Proyecto'),
                Tables\Filters\SelectFilter::make('provider')
                    ->relationship('provider', 'name')
                    ->label('Proveedor'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('assign')
                    ->label('Asignar a proyecto')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Forms\Components\Select::make('project_id')
                            ->label('Proyecto')
                            ->options(fn (): array => Project::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (Expense $record, array $data): void {
                        $record->update([
                            'project_id' => $data['project_id'],
                            'temporal' => false,
                        ]);
                    })
                    ->color('success')
                    ->requiresConfirmation()
                    ->successNotificationTitle('Gasto asignado correctamente')
                    ->visible(fn (Expense $record): bool => $record->temporal),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('assign_to_project')
                        ->label('Asignar a proyecto')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('project_id')
                                ->label('Proyecto')
                                ->options(fn (): array => Project::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(function (Expense $expense) use ($data): void {
                                $expense->update([
                                    'project_id' => $data['project_id'],
                                    'temporal' => false,
                                ]);
                            });
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->successNotificationTitle('Gastos asignados correctamente')
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
            'index' => Pages\ListTemporalExpenses::route('/'),
            //'create' => Pages\CreateTemporalExpense::route('/create'),
            //'edit' => Pages\EditTemporalExpense::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('temporal', true)
            ->whereNull('project_id')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('temporal', true)
            ->whereNull('project_id');
    }
}
