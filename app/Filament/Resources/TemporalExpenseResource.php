<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TemporalExpenseResource\Pages;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Project;
use App\Models\Provider;
use App\Services\Receipts\XmlReceiptParser;
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
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

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
                        Tables\Columns\TextColumn::make('related_invoice')
                            ->label('Factura relacionada')
                            ->state(function (?Expense $record) {
                                if (!$record || $record->document_type !== 'nota_credito') {
                                    return null;
                                }
                                
                                $info = self::getRelatedInvoiceInfo($record);
                                if (!$info) {
                                    return null;
                                }
                                
                                $text = 'Ref: ' . $info['voucher'];
                                $projectText = $info['project'] ?? 'Sin proyecto';
                                $text .= '<br/><strong><small>' . htmlspecialchars($projectText, ENT_QUOTES, 'UTF-8') . '</small></strong>';
                                return new HtmlString($text);
                            })
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-o-document-text')
                            ->visible(fn (?Expense $record): bool => 
                                $record !== null && $record->document_type === 'nota_credito'
                            ),
                        
                    ])->space(1)->grow(true),
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('amount')
                            ->label('Monto')
                            ->money('CRC', locale: 'es_CR')
                            ->weight('bold')
                            ->alignEnd()
                            ->sortable(),
                        Tables\Columns\TextColumn::make('usd_indicator')
                            ->label('')
                            ->state(fn (?Expense $record): ?string => $record && self::isUsdConverted($record) ? 'USD' : null)
                            ->badge()
                            ->color('warning')
                            ->icon('heroicon-o-currency-dollar')
                            ->alignEnd()
                            ->formatStateUsing(fn (?string $state): string => $state ? 'Convertido de USD' : '')
                            ->tooltip(fn (?Expense $record): ?string => $record ? self::getUsdConversionTooltip($record) : null)
                            ->visible(fn (?Expense $record): bool => $record !== null && self::isUsdConverted($record)),
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
                            }),
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
                Tables\Filters\Filter::make('usd_converted')
                    ->label('Convertido de USD')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('concept', 'like', '%Convertido de USD%')
                              ->orWhere('concept', 'like', '%MONEDA: USD%')
                              ->orWhere('concept', 'like', '%usando TC:%')
                    )
                    ->toggle()
                    ->default(false),
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo de documento')
                    ->options([
                        'factura' => 'Factura',
                        'nota_credito' => 'Nota de Crédito',
                        'nota_debito' => 'Nota de Débito',
                        'tiquete' => 'Tiquete',
                    ]),
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
                Tables\Actions\Action::make('aplicar_al_gasto')
                    ->label('Aplicar al gasto')
                    ->icon('heroicon-o-minus-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Aplicar nota de crédito al gasto')
                    ->modalDescription(fn (Expense $record): string => 
                        'Esta nota de crédito se aplicará al gasto relacionado. El monto del gasto se reducirá en ' . 
                        '₡' . number_format((float) $record->amount, 2, '.', ',') . '.'
                    )
                    ->action(function (Expense $creditNote, Tables\Actions\Action $action): void {
                        try {
                            if (!$creditNote->clave) {
                                throw new \Exception('La nota de crédito no tiene una clave de referencia válida.');
                            }

                            // Find the related expense by clave (which contains the original invoice's clave)
                            $relatedExpense = Expense::query()
                                ->where('provider_id', $creditNote->provider_id)
                                ->where('clave', $creditNote->clave)
                                ->where('id', '!=', $creditNote->id)
                                ->where('document_type', '!=', 'nota_credito') // Don't match other credit notes
                                ->first();

                            if (!$relatedExpense) {
                                throw new \Exception(
                                    "No se encontró un gasto relacionado con la clave: {$creditNote->clave}. " .
                                    "Verifique que el gasto original exista y tenga el mismo proveedor."
                                );
                            }

                            Log::info('Applying credit note to expense', [
                                'credit_note_id' => $creditNote->id,
                                'related_expense_id' => $relatedExpense->id,
                                'clave' => $creditNote->clave,
                            ]);

                            // Calculate new amount (original - credit)
                            $originalAmount = (float) $relatedExpense->amount;
                            $creditAmount = (float) $creditNote->amount;
                            $newAmount = $originalAmount - $creditAmount;

                            // Get credit reason from XML if available
                            $creditReason = self::extractCreditReason($creditNote);

                            // Build updated concept
                            $oldConcept = $relatedExpense->concept ?? '';
                            $updatedConcept = $oldConcept . '<br/><small style="color:#dc3545;">[NOTA DE CRÉDITO: ' . 
                                htmlspecialchars($creditReason, ENT_QUOTES, 'UTF-8') . 
                                ' por ₡' . number_format($creditAmount, 2, '.', ',') . 
                                ' - Comprobante: ' . htmlspecialchars($creditNote->voucher, ENT_QUOTES, 'UTF-8') . ']</small>';

                            // Handle attachments - merge credit note attachments with original expense
                            $currentAttachment = $relatedExpense->attachment;
                            
                            // Normalize current attachment to array
                            $currentAttachments = [];
                            if (is_array($currentAttachment)) {
                                $currentAttachments = $currentAttachment;
                            } else if (is_string($currentAttachment) && !empty($currentAttachment)) {
                                // Try to decode if it's JSON
                                $decoded = json_decode($currentAttachment, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    $currentAttachments = $decoded;
                                } else {
                                    $currentAttachments = [$currentAttachment];
                                }
                            }
                            
                            // Normalize credit note attachments to array
                            $creditAttachments = [];
                            if ($creditNote->attachment) {
                                if (is_array($creditNote->attachment)) {
                                    $creditAttachments = $creditNote->attachment;
                                } else if (is_string($creditNote->attachment)) {
                                    $decoded = json_decode($creditNote->attachment, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        $creditAttachments = $decoded;
                                    } else {
                                        $creditAttachments = [$creditNote->attachment];
                                    }
                                }
                            }
                            
                            // Merge both arrays, ensuring no duplicates
                            $allAttachments = array_merge($currentAttachments, $creditAttachments);
                            $attachmentPayload = array_values(array_unique($allAttachments));
                            
                            Log::info('Merging attachments', [
                                'related_expense_id' => $relatedExpense->id,
                                'current_attachments_count' => count($currentAttachments),
                                'credit_attachments_count' => count($creditAttachments),
                                'final_attachments_count' => count($attachmentPayload),
                                'current_attachments' => $currentAttachments,
                                'credit_attachments' => $creditAttachments,
                                'final_attachments' => $attachmentPayload,
                            ]);

                            // Update the related expense
                            $relatedExpense->amount = $newAmount >= 0 ? number_format($newAmount, 4, '.', '') : '0.0000';
                            $relatedExpense->concept = $updatedConcept;
                            $relatedExpense->attachment = $attachmentPayload;
                            $relatedExpense->save();

                            Log::info('Updated related expense', [
                                'expense_id' => $relatedExpense->id,
                                'original_amount' => $originalAmount,
                                'credit_amount' => $creditAmount,
                                'new_amount' => $newAmount,
                            ]);

                            // Mark the credit note as non-temporal and assign it to the same project
                            $creditNote->temporal = false;
                            $creditNote->project_id = $relatedExpense->project_id;
                            $creditNote->save();

                            Log::info('Marked credit note as non-temporal', [
                                'credit_note_id' => $creditNote->id,
                                'project_id' => $relatedExpense->project_id,
                            ]);

                            Notification::make()
                                ->title('Nota de crédito aplicada correctamente')
                                ->body(
                                    'El gasto ' . $relatedExpense->voucher . ' fue ajustado de ₡' . 
                                    number_format($originalAmount, 2, '.', ',') . 
                                    ' a ₡' . number_format(max(0, $newAmount), 2, '.', ',') . '.'
                                )
                                ->success()
                                ->send();
                            
                            $action->getLivewire()->dispatch('temporal-expenses-updated');
                            
                            // Refresh the page to update navigation badge
                            $action->getLivewire()->redirect(static::getUrl('index'));
                        } catch (\Throwable $e) {
                            Log::error('Error applying credit note to expense', [
                                'credit_note_id' => $creditNote->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            
                            Notification::make()
                                ->title('Error al aplicar nota de crédito')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Expense $record): bool => 
                        $record->temporal && $record->document_type === 'nota_credito'
                    ),
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
                        
                        // Refresh the page to update navigation badge
                        $action->getLivewire()->redirect(static::getUrl('index'));
                    })
                    ->color('success')
                    ->requiresConfirmation()
                    ->successNotificationTitle('Gasto asignado correctamente')
                    ->visible(fn (Expense $record): bool => 
                        $record->temporal && $record->document_type !== 'nota_credito'
                    ),
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
                            // Count credit notes before filtering
                            $creditNotesCount = $records->filter(fn (Expense $expense) => 
                                $expense->document_type === 'nota_credito'
                            )->count();
                            
                            // Filter out credit notes from the records
                            $regularExpenses = $records->filter(function (Expense $expense) {
                                return $expense->document_type !== 'nota_credito';
                            });
                            
                            $assignedCount = 0;
                            $regularExpenses->each(function (Expense $expense) use ($data, &$assignedCount): void {
                                $expense->update([
                                    'project_id' => $data['project_id'],
                                    'expense_type_id' => $data['expense_type_id'],
                                    'temporal' => false,
                                ]);
                                $assignedCount++;
                            });

                            // Show notification about credit notes being ignored
                            if ($creditNotesCount > 0) {
                                Notification::make()
                                    ->title('Notas de crédito ignoradas')
                                    ->body("{$creditNotesCount} nota(s) de crédito fueron ignoradas. Deben aplicarse manualmente usando la acción 'Aplicar al gasto'.")
                                    ->warning()
                                    ->send();
                            }
                            
                            // Show success notification for assigned expenses
                            if ($assignedCount > 0) {
                                Notification::make()
                                    ->title('Gastos asignados correctamente')
                                    ->body("Se asignaron {$assignedCount} gasto(s) al proyecto.")
                                    ->success()
                                    ->send();
                            }

                            $action->getLivewire()->dispatch('temporal-expenses-updated');
                            
                            // Refresh the page to update navigation badge
                            $action->getLivewire()->redirect(static::getUrl('index'));
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Asignar gastos a proyecto')
                        ->modalDescription(function (Collection $records) {
                            $creditNotesCount = $records->filter(fn (Expense $expense) => 
                                $expense->document_type === 'nota_credito'
                            )->count();
                            
                            $regularCount = $records->count() - $creditNotesCount;
                            
                            if ($creditNotesCount > 0) {
                                return "Se asignarán {$regularCount} gastos. {$creditNotesCount} nota(s) de crédito serán ignoradas (deben aplicarse manualmente).";
                            }
                            
                            return "Se asignarán {$regularCount} gasto(s) al proyecto seleccionado.";
                        })
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
        // Include all temporal expenses (both regular expenses and credit notes)
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

    protected static function extractCreditReason(Expense $creditNote): string
    {
        try {
            // Find XML attachment
            $xmlPath = self::findXmlAttachment($creditNote);
            
            if (!$xmlPath || !Storage::disk('public')->exists($xmlPath)) {
                return 'Nota de crédito aplicada';
            }

            $xmlContent = Storage::disk('public')->get($xmlPath);
            
            if (empty($xmlContent)) {
                return 'Nota de crédito aplicada';
            }

            // Parse XML to get credit reason
            $parser = app(XmlReceiptParser::class);
            $parsed = $parser->parse($xmlContent);

            return $parsed['reference_info']['razon'] ?? 'Nota de crédito aplicada';
        } catch (\Throwable $e) {
            Log::warning('Could not extract credit reason from credit note', [
                'expense_id' => $creditNote->id,
                'error' => $e->getMessage(),
            ]);
            return 'Nota de crédito aplicada';
        }
    }

    protected static function findXmlAttachment(Expense $expense): ?string
    {
        $attachments = self::normalizeAttachments($expense->attachment);
        
        foreach ($attachments as $path) {
            if (str_ends_with(strtolower($path), '.xml')) {
                return $path;
            }
        }

        return null;
    }

    protected static function getRelatedInvoiceInfo(Expense $creditNote): ?array
    {
        if (!$creditNote->clave || !$creditNote->provider_id) {
            return null;
        }

        try {
            // Find the related invoice by clave (which contains the original invoice's clave)
            // Search both temporal and assigned expenses
            $relatedExpense = Expense::query()
                ->with('project')
                ->where('provider_id', $creditNote->provider_id)
                ->where('clave', $creditNote->clave)
                ->where('id', '!=', $creditNote->id)
                ->where('document_type', '!=', 'nota_credito') // Don't match other credit notes
                ->orderBy('created_at', 'desc') // Get the most recent one if multiple exist
                ->first();

            if (!$relatedExpense) {
                return null;
            }

            return [
                'voucher' => $relatedExpense->voucher,
                'project' => $relatedExpense->project?->name,
            ];
        } catch (\Throwable $e) {
            Log::warning('Could not find related invoice for credit note', [
                'credit_note_id' => $creditNote->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if an expense was converted from USD
     */
    protected static function isUsdConverted(?Expense $record): bool
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
    protected static function getUsdConversionTooltip(?Expense $record): ?string
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
