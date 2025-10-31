<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Expense;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Guava\FilamentNestedResources\Concerns\NestedPage;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Resources\Components\Tab;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Forms\Components\DatePicker;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\Tables\PhoneColumn;
use Ysfkaya\FilamentPhoneInput\Infolists\PhoneEntry;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Altwaireb\World\Models\State;
use Altwaireb\World\Models\City;
use Illuminate\Support\Collection;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Column;
use Illuminate\Support\Facades\Storage;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;
use Illuminate\Support\Str;


class ManageProjectExpenses extends ManageRelatedRecords
{
    use NestedPage;

    protected static string $resource = ProjectResource::class;

    protected static string $relationship = 'expenses';

    protected static ?string $navigationIcon = 'heroicon-c-arrow-trending-down';

    public static function getNavigationLabel(): string
    {
        return 'Gastos';
    }

    public function getTitle(): string | Htmlable
    {
        return __('Gastos - '. $this->record->name);
    }

    public function getTabs(): array
    {
        $tabs = [];

        $tabs[] = Tab::make('Todas') 
            ->badge(Expense::where('project_id', $this->record->id)->count())
            ->icon('heroicon-s-arrow-right-circle')
            ->badgeColor('info');
    
        $tabs[] = Tab::make('Pagas') 
            ->badge(Expense::where('project_id', $this->record->id)->where('type', 'paid')->count())
            ->icon('heroicon-s-arrow-trending-up') 
            ->badgeColor('success')
            ->modifyQueryUsing(function ($query) {
                return $query->where('type', 'paid');
            });
        $tabs[] = Tab::make('Por pagar') 
            ->badge(Expense::where('project_id', $this->record->id)->where('type', 'unpaid')->count())
            ->icon('heroicon-s-arrow-trending-down')
            ->badgeColor('warning')
            ->modifyQueryUsing(function ($query) {
                return $query->where('type', 'unpaid');
            });
    

    
        return $tabs;
    }

    public function form(Form $form): Form
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
                                    ->relationship(name: 'expenseType', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre')
                                            ->required()
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Proveedor')
                    ->schema([
                        Forms\Components\Select::make('provider_id')
                            ->label('Proveedor')
                            ->relationship(name: 'provider', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                PhoneInput::make('phone')
                                    ->initialCountry('cr'),
                                Forms\Components\TextInput::make('email')
                                    ->required()
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\Select::make('country_id')
                                    ->label('País')
                                    ->relationship(name: 'country', titleAttribute: 'name')
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
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Adjunto')
                    ->schema([
                        FileUpload::make('attachment')
                            ->disk('public')
                            ->directory('expenses/attachments')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->label('Archivo adjunto (PDF o imagen)')
                            ->maxSize(10240)
                            ->openable()
                            ->downloadable()
                            ->nullable(),
                    ])
                    ->columns(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Gastos')
            ->description('Lista de gastos')
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
                            ->searchable()
                            ->extraAttributes(['class' => 'text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400'])
                            ->toggleable(),
                        Tables\Columns\TextColumn::make('provider.name')
                            ->label('Proveedor')
                            ->weight('bold')
                            ->toggleable()
                            ->wrap()
                            ->searchable(),
                        Tables\Columns\TextColumn::make('concept')
                            ->label('Concepto')
                            ->formatStateUsing(fn ($state) => strip_tags((string) $state))
                            ->limit(80)
                            ->tooltip(fn (Expense $record) => $this->formatConceptTooltip($record->concept))
                            ->extraAttributes(['class' => 'text-sm text-gray-600 dark:text-gray-300'])
                            ->searchable(),
                    ])->space(1)->grow(true),
                    Stack::make([
                        Tables\Columns\TextColumn::make('amount')
                            ->label('Monto')
                            ->money('CRC', locale: 'es_CR')
                            ->weight('bold')
                            ->alignEnd()
                            ->sortable()
                            ->searchable()
                            ->summarize(Sum::make()->label('Total')->money('CRC', locale: 'es_CR')),
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
                            })
                            ->searchable(),
                        Tables\Columns\TextColumn::make('expenseType.name')
                            ->label('Categoría')
                            ->badge()
                            ->alignEnd()
                            ->color('primary')
                            ->toggleable()
                            ->searchable()
                            ->sortable(),
                    ])->space(1)->extraAttributes(['class' => 'items-end text-right'])->grow(false),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('expenseType')
                    ->label('Tipo')
                    ->relationship('expenseType', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('Desde'),
                        DatePicker::make('created_until')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Crear gasto'),
                //Tables\Actions\AssociateAction::make(),
                
            ])
            ->actions([
                Action::make('view_attachment')
                    ->label('Ver adjunto')
                    ->url(fn (Expense $record) => $this->buildPrimaryAttachmentUrl($record))
                    ->openUrlInNewTab()
                    ->color('warning')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (Expense $record) => $this->primaryAttachmentExists($record)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                //Tables\Actions\DissociateAction::make(),
                //Tables\Actions\DeleteAction::make(),
                ActivityLogTimelineTableAction::make('Activities')
                    ->label('Actividad')
                    ->color('info')
                    ->limit(15),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DissociateBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                    FilamentExportBulkAction::make('Exportar'),
                ]),
            ]);
    }


    
    protected function formatConceptTooltip(?string $html): ?string
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
            $text = Str::of($html)
                ->replace('<br />', "\r\n")
                ->replace('<br/>', "\r\n")
                ->replace('<br>', "\r\n")
                ->toString();

            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim($text);

            return $text !== '' ? $text : null;
        }

        return implode("\r\n", $items);
    }

    protected function buildPrimaryAttachmentUrl(Expense $record): ?string
    {
        $path = $this->getPrimaryAttachmentPath($record->attachment);

        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    protected function primaryAttachmentExists(Expense $record): bool
    {
        $path = $this->getPrimaryAttachmentPath($record->attachment);

        return $path ? Storage::disk('public')->exists($path) : false;
    }

    protected function getPrimaryAttachmentPath($attachment): ?string
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
