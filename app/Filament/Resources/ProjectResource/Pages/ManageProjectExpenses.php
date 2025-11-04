<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Expense;
use App\Models\Project;
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
            ->badge(Expense::where('project_id', $this->record->id)->where(function ($q) {
                $q->where('document_type', '!=', 'nota_credito')
                  ->orWhereNull('document_type');
            })->count())
            ->icon('heroicon-s-arrow-right-circle')
            ->badgeColor('info')
            ->modifyQueryUsing(function ($query) {
                return $query->where(function ($q) {
                    $q->where('document_type', '!=', 'nota_credito')
                      ->orWhereNull('document_type');
                });
            });
    
        $tabs[] = Tab::make('Pagas') 
            ->badge(Expense::where('project_id', $this->record->id)->where('type', 'paid')->where(function ($q) {
                $q->where('document_type', '!=', 'nota_credito')
                  ->orWhereNull('document_type');
            })->count())
            ->icon('heroicon-s-arrow-trending-up') 
            ->badgeColor('success')
            ->modifyQueryUsing(function ($query) {
                return $query->where('type', 'paid')->where(function ($q) {
                    $q->where('document_type', '!=', 'nota_credito')
                      ->orWhereNull('document_type');
                });
            });
        $tabs[] = Tab::make('Por pagar') 
            ->badge(Expense::where('project_id', $this->record->id)->where('type', 'unpaid')->where(function ($q) {
                $q->where('document_type', '!=', 'nota_credito')
                  ->orWhereNull('document_type');
            })->count())
            ->icon('heroicon-s-arrow-trending-down')
            ->badgeColor('warning')
            ->modifyQueryUsing(function ($query) {
                return $query->where('type', 'unpaid')->where(function ($q) {
                    $q->where('document_type', '!=', 'nota_credito')
                      ->orWhereNull('document_type');
                });
            });
    

    
        return $tabs;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Asignación')
                    ->description('Asigna este gasto a un proyecto y proveedor')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('project_id')
                                    ->label('Proyecto')
                                    ->relationship(name: 'project', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false),
                                Forms\Components\Select::make('provider_id')
                                    ->label('Proveedor')
                                    ->relationship(name: 'provider', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->native(false)
                                    ->createOptionForm([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                        PhoneInput::make('phone')
                                                    ->initialCountry('cr')
                                                    ->columnSpan(1),
                                        Forms\Components\TextInput::make('email')
                                            ->required()
                                            ->email()
                                                    ->maxLength(255)
                                                    ->columnSpan(1),
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
                                                    ->required()
                                                    ->columnSpan(1),
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
                                                    ->required()
                                                    ->columnSpan(1),
                                        Forms\Components\Select::make('city_id')
                                            ->options(fn (Get $get): Collection => City::query()
                                                ->where('state_id', $get('state_id'))
                                                ->pluck('name', 'id')
                                            )
                                            ->label('Ciudad')
                                            ->searchable()
                                            ->preload()
                                                    ->required()
                                                    ->columnSpan(1),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(false),
                
                Forms\Components\Section::make('Información del Gasto')
                    ->description('Detalles principales del comprobante')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('voucher')
                                    ->label('Comprobante')
                                    ->required()
                                    ->maxLength(50)
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('clave')
                                    ->label('Clave')
                                    ->maxLength(50)
                                    ->columnSpan(1),
                                
                                Forms\Components\TextInput::make('amount')
                                    ->label('Monto')
                                    ->prefix('₡')
                                    ->required()
                                    ->numeric()
                                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                                    ->columnSpan(1),
                            ]),
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DateTimePicker::make('date')
                                    ->label('Fecha')
                                    ->required()
                                    ->default(now())
                                    ->displayFormat('d/m/Y H:i')
                                    ->columnSpan(1),
                                Forms\Components\Select::make('type')
                                    ->label('Estado')
                                    ->required()
                                    ->options([
                                        'paid' => 'Pagado',
                                        'unpaid' => 'No pagado',
                                    ])
                                    ->native(false)
                                    ->columnSpan(1),
                                Forms\Components\Select::make('expense_type_id')
                                    ->label('Categoría de gasto')
                                    ->relationship(name: 'expenseType', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->native(false)
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->columnSpan(1),
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
                                'style' => 'max-height: 200px; overflow: auto;',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(false),
                
                Forms\Components\Section::make('Documentos Adjuntos')
                    ->description('Adjunta comprobantes o documentos relacionados')
                    ->schema([
                        FileUpload::make('attachment')
                            ->disk('public')
                            ->directory('expenses/attachments')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->label('Archivos adjuntos')
                            ->helperText('Formatos permitidos: PDF, imágenes. Tamaño máximo: 10MB')
                            ->maxSize(10240)
                            ->openable()
                            ->downloadable()
                            ->previewable()
                            ->imageEditor()
                            ->multiple()
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->where(function ($q) {
                $q->where('document_type', '!=', 'nota_credito')
                  ->orWhereNull('document_type');
            }))
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
                        Tables\Columns\TextColumn::make('usd_indicator')
                            ->label('')
                            ->state(fn (?Expense $record): ?string => $record && $this->isUsdConverted($record) ? 'USD' : null)
                            ->badge()
                            ->color('warning')
                            ->icon('heroicon-o-currency-dollar')
                            ->alignEnd()
                            ->formatStateUsing(fn (?string $state): string => $state ? 'Convertido de USD' : '')
                            ->tooltip(fn (?Expense $record): ?string => $record ? $this->getUsdConversionTooltip($record) : null)
                            ->visible(fn (?Expense $record): bool => $record !== null && $this->isUsdConverted($record)),
                        Tables\Columns\TextColumn::make('document_type')
                            ->label('Tipo de documento')
                            ->badge()
                            ->alignEnd()
                            ->color(fn (?string $state): string => match ($state) {
                                'factura' => 'primary',
                                'nota_credito' => 'danger',
                                'nota_debito' => 'warning',
                                'tiquete' => 'info',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'factura' => 'Factura',
                                'nota_credito' => 'Nota de Crédito',
                                'nota_debito' => 'Nota de Débito',
                                'tiquete' => 'Tiquete',
                                null => 'Sin tipo',
                                default => ucfirst($state ?? 'Desconocido'),
                            })
                            ->toggleable()
                            ->searchable(),
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
                Action::make('view_attachments')
                    ->label(fn (Expense $record) => $this->getAttachmentActionLabel($record))
                    ->color('warning')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (Expense $record) => !empty($record->attachment))
                    ->modal()
                    ->modalHeading('Ver documentos adjuntos')
                    ->modalContent(function (Expense $record) {
                        $attachments = $this->getAllAttachmentPaths($record);
                        
                        if (empty($attachments)) {
                            return new \Illuminate\Support\HtmlString('<p>No hay documentos adjuntos.</p>');
                        }
                        
                        // Show list of attachments (works for both single and multiple)
                        $html = '<div class="space-y-2">';
                        foreach ($attachments as $index => $path) {
                            // Path should already be normalized from getAllAttachmentPaths
                            // But ensure it's clean for URL generation - remove quotes and whitespace
                            $cleanPath = trim($path, " \t\n\r\0\x0B'\"");
                            $cleanPath = ltrim($cleanPath, '/');
                            $cleanPath = preg_replace('#^storage/#', '', $cleanPath);
                            
                            // Verify file exists and try to find the correct path if needed
                            $foundPath = $cleanPath;
                            $fileExists = Storage::disk('public')->exists($cleanPath);
                            
                            // If file doesn't exist, try to find it recursively
                            if (!$fileExists) {
                                $filename = basename($cleanPath);
                                
                                // Try searching recursively in expenses/attachments
                                $foundInExpenses = $this->findFileRecursively('expenses/attachments', $filename);
                                if ($foundInExpenses) {
                                    $foundPath = $foundInExpenses;
                                    $fileExists = true;
                                } else {
                                    // Try searching recursively in gmail-receipts
                                    $foundInGmail = $this->findFileRecursively('gmail-receipts', $filename);
                                    if ($foundInGmail) {
                                        $foundPath = $foundInGmail;
                                        $fileExists = true;
                                    } else {
                                        // Try common variations
                                        $variations = [
                                            $cleanPath,
                                            'expenses/attachments/' . $filename,
                                            'gmail-receipts/' . $filename,
                                        ];
                                        
                                        foreach ($variations as $variation) {
                                            if (Storage::disk('public')->exists($variation)) {
                                                $foundPath = $variation;
                                                $fileExists = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            // Generate URL using the found/existing path
                            // Ensure path is properly encoded and no quotes
                            $urlPath = 'storage/' . ltrim($foundPath, '/');
                            $url = asset($urlPath);
                            $fileName = basename($foundPath);
                            
                            // Try to identify which document is the credit note
                            // Credit notes are typically added last when applying "Aplicar al gasto"
                            // We can check if the concept contains "[NOTA DE CRÉDITO" to help identify
                            $hasCreditNoteInfo = strpos($record->concept ?? '', '[NOTA DE CRÉDITO') !== false;
                            
                            // If there are multiple attachments and we found credit note info in concept,
                            // and this is not the first one, it's likely the credit note
                            $documentLabel = '';
                            if (count($attachments) > 1 && $hasCreditNoteInfo) {
                                if ($index === count($attachments) - 1) {
                                    $documentLabel = '📄 Nota de crédito: ' . $fileName;
                                } else {
                                    $documentLabel = '📋 Factura original: ' . $fileName;
                                }
                            } else {
                                $documentLabel = '📄 Documento ' . ($index + 1) . ': ' . $fileName;
                            }
                            
                            $badgeHtml = $fileExists 
                                ? '<span class="text-xs text-green-600 dark:text-green-400">✓ Existe</span>'
                                : '<span class="text-xs text-yellow-600 dark:text-yellow-400">⚠ Verificar</span>';
                            
                            // Clean URL to remove any problematic characters
                            $cleanUrl = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            
                            $html .= sprintf(
                                '<div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium">%s</span>
                                        <span class="text-xs text-gray-500 mt-1">%s</span>
                                        %s
                                    </div>
                                    <a href="%s" target="_blank" class="inline-flex items-center px-3 py-1 text-sm font-medium text-white bg-primary-600 rounded hover:bg-primary-700">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Ver
                                    </a>
                                </div>',
                                htmlspecialchars($documentLabel, ENT_QUOTES, 'UTF-8'),
                                htmlspecialchars($foundPath, ENT_QUOTES, 'UTF-8'),
                                $badgeHtml,
                                $cleanUrl
                            );
                        }
                        $html .= '</div>';
                        
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
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
        return $this->hasAnyAttachment($record);
    }

    protected function hasAnyAttachment(Expense $record): bool
    {
        // First, check if attachment field has any value at all
        if (empty($record->attachment)) {
            return false;
        }
        
        // Return true if there's any attachment value, even if we can't verify file existence
        // The modal will handle showing "no attachments" if files don't exist
        return true;
    }

    protected function getAllAttachmentPaths(Expense $record): array
    {
        if (empty($record->attachment)) {
            return [];
        }

        // With the 'array' cast, Laravel should automatically deserialize JSON to array
        // But we need to handle cases where data might be in different formats
        $paths = [];
        
        // Get raw attribute to check actual format
        $rawAttachment = $record->getAttributes()['attachment'] ?? null;
        
        // Try to get from cast first
        if (is_array($record->attachment) && !empty($record->attachment)) {
            $paths = $record->attachment;
        } else if (!empty($rawAttachment)) {
            // Raw value exists, try to parse it
            if (is_string($rawAttachment)) {
                // Try to decode JSON first
                $decoded = json_decode($rawAttachment, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $paths = $decoded;
                } else {
                    // It's a plain string, treat as single attachment
                    $paths = [$rawAttachment];
                }
            } else if (is_array($rawAttachment)) {
                $paths = $rawAttachment;
            }
        }
        
        // Log for debugging
        if (empty($paths)) {
            \Log::warning('No attachment paths found for expense', [
                'expense_id' => $record->id,
                'attachment_raw' => $rawAttachment,
                'attachment_cast' => $record->attachment,
                'attachment_type_raw' => gettype($rawAttachment),
                'attachment_type_cast' => gettype($record->attachment),
            ]);
        } else {
            \Log::info('Found attachment paths', [
                'expense_id' => $record->id,
                'paths_count' => count($paths),
                'paths' => $paths,
            ]);
        }

        // Filter out empty values and find files that actually exist
        return collect($paths)
            ->map(function ($path) {
                // Normalize the path
                if (empty($path) || !is_string($path)) {
                    return null;
                }
                
                // Clean the path - remove quotes, leading slashes, and whitespace
                $cleanPath = trim($path, " \t\n\r\0\x0B'\"");
                $cleanPath = ltrim($cleanPath, '/');
                
                // Remove "storage/" prefix if present
                $cleanPath = preg_replace('#^storage/#', '', $cleanPath);
                
                // Check if file exists as-is (most common case)
                if (Storage::disk('public')->exists($cleanPath)) {
                    return $cleanPath;
                }
                
                // Build list of variations to try
                $variations = [$cleanPath];
                $filename = basename($cleanPath);
                
                // If path is just a filename (no directory), search recursively
                if (strpos($cleanPath, '/') === false) {
                    // Search in expenses/attachments recursively
                    $foundInExpenses = $this->findFileRecursively('expenses/attachments', $filename);
                    if ($foundInExpenses) {
                        return $foundInExpenses;
                    }
                    
                    // Search in gmail-receipts recursively
                    $foundInGmail = $this->findFileRecursively('gmail-receipts', $filename);
                    if ($foundInGmail) {
                        return $foundInGmail;
                    }
                    
                    // Try common directories
                    $variations[] = 'expenses/attachments/' . $cleanPath;
                    $variations[] = 'gmail-receipts/' . $cleanPath;
                } else {
                    // Path has directory structure
                    $dirPath = dirname($cleanPath);
                    
                    // Try exact path
                    $variations[] = $cleanPath;
                    
                    // Try with just filename in common directories
                    $variations[] = 'expenses/attachments/' . $filename;
                    $variations[] = 'gmail-receipts/' . $filename;
                    
                    // If path doesn't start with expenses/ or gmail-receipts/, try adding them
                    if (!str_starts_with($cleanPath, 'expenses/') && !str_starts_with($cleanPath, 'gmail-receipts/')) {
                        $variations[] = 'expenses/attachments/' . $cleanPath;
                        $variations[] = 'gmail-receipts/' . $cleanPath;
                    }
                    
                    // If path starts with expenses/attachments/ but file not found, try searching by filename
                    if (str_starts_with($cleanPath, 'expenses/attachments/')) {
                        $foundInExpenses = $this->findFileRecursively('expenses/attachments', $filename);
                        if ($foundInExpenses) {
                            return $foundInExpenses;
                        }
                    }
                    
                    // If path starts with gmail-receipts/ but file not found, try searching by filename
                    if (str_starts_with($cleanPath, 'gmail-receipts/')) {
                        $foundInGmail = $this->findFileRecursively('gmail-receipts', $filename);
                        if ($foundInGmail) {
                            return $foundInGmail;
                        }
                    }
                }
                
                // Remove duplicates and try each variation
                $variations = array_unique($variations);
                
                foreach ($variations as $variation) {
                    if (Storage::disk('public')->exists($variation)) {
                        return $variation;
                    }
                }
                
                // Last resort: search recursively by filename in both directories
                $foundInExpenses = $this->findFileRecursively('expenses/attachments', $filename);
                if ($foundInExpenses) {
                    return $foundInExpenses;
                }
                
                $foundInGmail = $this->findFileRecursively('gmail-receipts', $filename);
                if ($foundInGmail) {
                    return $foundInGmail;
                }
                
                // If not found, return the original cleaned path anyway
                // The URL will be generated and user can try to access it
                return $cleanPath;
            })
            ->filter() // Remove any null/empty values
            ->unique()
            ->values()
            ->toArray();
    }

    protected function findFileRecursively(string $directory, string $filename): ?string
    {
        try {
            // Check if directory exists
            if (!Storage::disk('public')->exists($directory)) {
                return null;
            }
            
            // Get all files recursively in the directory
            $files = Storage::disk('public')->allFiles($directory);
            
            // Find file matching the filename
            foreach ($files as $file) {
                if (basename($file) === $filename) {
                    return $file;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error searching for file recursively', [
                'directory' => $directory,
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
        }
        
        return null;
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

    protected function getAttachmentActionLabel(Expense $record): string
    {
        $count = count($this->getAllAttachmentPaths($record));
        
        if ($count === 0) {
            return 'Ver adjunto';
        } elseif ($count === 1) {
            return 'Ver adjunto';
        } else {
            return "Ver adjuntos ({$count})";
        }
    }

    /**
     * Check if an expense was converted from USD
     */
    protected function isUsdConverted(?Expense $record): bool
    {
        if (!$record || empty($record->concept)) {
            return false;
        }

        $concept = (string) $record->concept;
        
        // Check for conversion indicators in the concept
        return str_contains($concept, 'Convertido de USD') ||
               str_contains($concept, 'MONEDA: USD') ||
               str_contains($concept, 'usando TC:');
    }

    /**
     * Get USD conversion tooltip information
     */
    protected function getUsdConversionTooltip(?Expense $record): ?string
    {
        if (!$record || empty($record->concept)) {
            return null;
        }

        $concept = (string) $record->concept;
        
        // Strip HTML tags for pattern matching
        $conceptText = strip_tags($concept);
        
        // Extract conversion details from concept
        // Actual format: "[✓ Convertido de USD $248.60 a ₡123,571.60 usando tipo de cambio(BCCR): 497.2500]"
        // After strip_tags: "[✓ Convertido de USD $248.60 a ₡123,571.60 usando tipo de cambio(BCCR): 497.2500]"
        $patterns = [
            // Pattern 1: With checkmark and brackets, new format with "tipo de cambio(BCCR):"
            '/\[✓\s*Convertido de USD\s*\$([\d,\.]+)\s*a\s*₡([\d,\.]+)\s*usando\s*tipo\s*de\s*cambio\s*\(BCCR\):\s*([\d,\.]+)\]/i',
            // Pattern 2: Without checkmark but with brackets, new format
            '/\[Convertido de USD\s*\$([\d,\.]+)\s*a\s*₡([\d,\.]+)\s*usando\s*tipo\s*de\s*cambio\s*\(BCCR\):\s*([\d,\.]+)\]/i',
            // Pattern 3: Without brackets, new format
            '/Convertido de USD\s*\$([\d,\.]+)\s*a\s*₡([\d,\.]+)\s*usando\s*tipo\s*de\s*cambio\s*\(BCCR\):\s*([\d,\.]+)/i',
            // Pattern 4: With checkmark and brackets, old format "TC:"
            '/\[✓\s*Convertido de USD\s*\$([\d,\.]+)\s*a\s*₡([\d,\.]+)\s*usando\s*TC:\s*([\d,\.]+)\]/i',
            // Pattern 5: Without checkmark but with brackets, old format
            '/\[Convertido de USD\s*\$([\d,\.]+)\s*a\s*₡([\d,\.]+)\s*usando\s*TC:\s*([\d,\.]+)\]/i',
            // Pattern 6: Without brackets, old format
            '/Convertido de USD\s*\$([\d,\.]+)\s*a\s*₡([\d,\.]+)\s*usando\s*TC:\s*([\d,\.]+)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $conceptText, $matches)) {
                $usdAmount = $matches[1];
                $crcAmount = $matches[2];
                $exchangeRateRaw = $matches[3];
                
                // Clean up amounts - remove commas for parsing
                $usdAmountClean = str_replace(',', '', $usdAmount);
                $crcAmountClean = str_replace(',', '', $crcAmount);
                $exchangeRateClean = str_replace(',', '', $exchangeRateRaw);
                
                // Format exchange rate to 2 decimals
                $exchangeRate = number_format((float) $exchangeRateClean, 2, '.', ',');
                
                // Format amounts with proper commas
                $usdFormatted = number_format((float) $usdAmountClean, 2, '.', ',');
                $crcFormatted = number_format((float) $crcAmountClean, 2, '.', ',');
                
                // Format: Original $5.00 x TC ₡531.25 -> ₡2,656.25
                return sprintf(
                    'Original $%s x TC ₡%s -> ₡%s',
                    $usdFormatted,
                    $exchangeRate,
                    $crcFormatted
                );
            }
        }
        
        // Fallback for other USD indicators
        if (str_contains($conceptText, 'MONEDA: USD') || str_contains($concept, 'MONEDA: USD')) {
            return 'Este gasto estaba originalmente en dólares (USD)';
        }
        
        return null;
    }
}
