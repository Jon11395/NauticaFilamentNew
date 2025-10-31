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
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Card;
use Filament\Tables\Columns\Layout\Split;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

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
                                    ->maxLength(50),
                                Forms\Components\DateTimePicker::make('date')
                                    ->label('Fecha')
                                    ->required(),
                            ]),
                        Forms\Components\RichEditor::make('concept')
                            ->label('Concepto')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'bulletList',
                                'orderedList',
                                'link',
                                'undo',
                                'redo',
                            ])
                            ->maxLength(5000)
                            ->extraAttributes([
                                'style' => 'max-height: 240px; overflow: auto;',
                            ])
                            ->columnSpanFull(),
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
                
                Split::make([
                    Stack::make([
                        Tables\Columns\TextColumn::make('voucher')
                            ->label('Comprobante')
                            ->sortable()
                            ->toggleable()
                            ->searchable(),
                        Tables\Columns\TextColumn::make('date')
                            ->label('Fecha')
                            ->dateTime('d/m/Y h:i A')
                            ->color('gray')
                            ->sortable()
                            ->extraAttributes(['class' => 'text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400'])
                            ->toggleable(),
                        Tables\Columns\TextColumn::make('provider.name')
                            ->label('Proveedor')
                            ->weight('bold')
                            ->toggleable(),
                        Tables\Columns\TextColumn::make('concept')
                            ->label('Concepto')
                            ->formatStateUsing(fn ($state) => strip_tags((string) $state))
                            ->limit(80)
                            ->tooltip(fn (Model $record) => self::formatConceptTooltip($record->concept))
                            ->extraAttributes(['class' => 'text-sm text-gray-600 dark:text-gray-300']),
                    ])->space(1)->grow(true),
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('amount')
                            ->label('Monto')
                            ->money('CRC', locale: 'es_CR')
                            ->weight('bold')
                            ->alignEnd()
                            ->sortable(),
                        Tables\Columns\TextColumn::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->alignEnd()
                            ->color(fn (string $state): string => match ($state) {
                                'paid' => 'success',
                                'unpaid' => 'warning',
                                default => 'secondary',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'paid' => 'Pagado',
                                'unpaid' => 'No pagado',
                                default => ucfirst($state),
                            }),
                    ])->space(1)->extraAttributes(['class' => 'items-end text-right'])->grow(false),
                ])->from('md'),
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
                Tables\Actions\Action::make('view_attachment')
                    ->label('Ver adjunto')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->url(fn (Expense $record) => self::buildPrimaryAttachmentUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Expense $record) => self::primaryAttachmentExists($record)),
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
                        Forms\Components\Select::make('expense_type_id')
                            ->label('Categoría')
                            ->options(fn (): array => ExpenseType::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (Expense $record, array $data, Tables\Actions\Action $action): void {
                        $record->update([
                            'project_id' => $data['project_id'],
                            'expense_type_id' => $data['expense_type_id'],
                            'temporal' => false,
                        ]);

                        $action->getLivewire()->dispatch('temporal-expenses-updated');
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
                            Forms\Components\Select::make('expense_type_id')
                                ->label('Categoría')
                                ->options(fn (): array => ExpenseType::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, Tables\Actions\BulkAction $action): void {
                            $records->each(function (Expense $expense) use ($data): void {
                                $expense->update([
                                    'project_id' => $data['project_id'],
                                    'expense_type_id' => $data['expense_type_id'],
                                    'temporal' => false,
                                ]);
                            });

                            $action->getLivewire()->dispatch('temporal-expenses-updated');
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

    protected static function formatConceptTooltip(?string $html): ?string
    {
        if (empty($html)) {
            return null;
        }

        $items = [];

        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches)) {
            foreach ($matches[1] as $item) {
                $clean = trim(strip_tags($item));

                if ($clean !== '') {
                    $items[] = '• ' . $clean;
                }
            }
        }

        if (empty($items)) {
            $text = trim(strip_tags(Str::of($html)->replace('<br />', "\r\n")));

            return $text !== '' ? $text : null;
        }

        return implode("\r\n", $items);
    }

    protected static function buildAttachmentLabels(Expense $record): array
    {
        $paths = self::normalizeAttachments($record->attachment);

        return collect($paths)
            ->map(fn (string $path) => [
                'label' => basename($path),
                'url' => asset('storage/' . ltrim($path, '/')),
            ])
            ->map(fn (array $data) => $data['label'])
            ->values()
            ->all();
    }

    protected static function normalizeAttachments($attachment): array
    {
        if (empty($attachment)) {
            return [];
        }

        return is_array($attachment) ? $attachment : [$attachment];
    }

    protected static function buildPrimaryAttachmentUrl(Expense $record): ?string
    {
        $path = self::getPrimaryAttachmentPath($record->attachment);

        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    protected static function primaryAttachmentExists(Expense $record): bool
    {
        $path = self::getPrimaryAttachmentPath($record->attachment);

        return $path ? Storage::disk('public')->exists($path) : false;
    }

    protected static function getPrimaryAttachmentPath($attachment): ?string
    {
        if (empty($attachment)) {
            return null;
        }

        $paths = is_array($attachment) ? $attachment : [$attachment];

        return collect($paths)
            ->filter()
            ->first();
    }
}
