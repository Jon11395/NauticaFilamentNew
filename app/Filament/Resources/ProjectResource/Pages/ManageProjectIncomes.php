<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use Filament\Forms;
use Filament\Tables;
use Filament\Actions;
use App\Models\Income;
use App\Models\Project;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Resources\ProjectResource;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Resources\Pages\ManageRelatedRecords;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Guava\FilamentNestedResources\Concerns\NestedPage;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Column;
use Illuminate\Support\Facades\Storage;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;




class ManageProjectIncomes extends ManageRelatedRecords
{

    use NestedPage;

    protected static string $resource = ProjectResource::class;

    protected static string $relationship = 'incomes';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    public static function getNavigationLabel(): string
    {
        return 'Ingresos';
    }

    public function getTitle(): string | Htmlable
    {
        return __('Ingresos - '. $this->record->name);
    }

    


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('bill_number')
                    ->label('# Factura')
                    ->required()
                    ->numeric()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('date')
                    ->label('Fecha')
                    ->required()
                    ->default(now()),
                Forms\Components\TextInput::make('bill_amount')
                    ->label('Monto')
                    ->prefix('₡')
                    ->default(0)
                    ->required()
                    ->numeric()
                    ->live(true)
                    ->afterStateUpdated(function ($state,Get $get, Set $set) {
                        $set('IVA', ($state * 0.13));
                        self::updateTotals($get, $set);
                    })
                    ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                Forms\Components\TextInput::make('IVA')
                    ->label('IVA')
                    ->prefix('₡')
                    ->default(0)
                    ->required()
                    ->numeric()
                    ->live(true)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::updateTotals($get, $set);
                    })
                    ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                Forms\Components\TextInput::make('retentions')
                    ->label('Retenciones')
                    ->prefix('₡')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->live(true)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::updateTotals($get, $set);
                    })
                    ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                Forms\Components\TextInput::make('total_deposited')
                    ->label('Total depositado')
                    ->readonly()
                    ->default(0)
                    ->prefix('₡')
                    ->afterStateHydrated(function (Get $get, Set $set) {
                        self::updateTotals($get, $set);
                    })
                    ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->autosize()
                    ->maxLength(1000),
                Forms\Components\FileUpload::make('attachment')
                    ->disk('public') // use the public disk, which points to storage/app/public
                    ->directory('incomes/attachments')
                    ->acceptedFileTypes(['application/pdf', 'image/*']) 
                    ->label('Archivo adjunto (PDF o imagen)')
                    ->maxSize(10240)
                    ->nullable()
                    //->multiple()
                    //->panelLayout('grid'),
            ]);
    }

    public static function updateTotals(Get $get, Set $set): void
    {
        $bill_amount = $get('bill_amount');
        $IVA = $get('IVA');
        $retentions = $get('retentions');

        $total = (($bill_amount + $IVA) - $retentions );
        
        $set('total_deposited', $total);
    }


    public function table(Table $table): Table
    {
        return $table
            ->heading('Ingresos')
            ->description('Lista de ingresos')
            ->recordTitleAttribute('project_id')
            ->columns([
            Tables\Columns\TextColumn::make('bill_number')
                ->label('# Factura')
                ->searchable()
                ->numeric()
                ->sortable(),
            Tables\Columns\TextColumn::make('date')
                ->label('Fecha')
                ->date()
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('bill_amount')
                ->label('Monto')
                ->money('CRC')
                ->searchable()
                ->sortable()
                ->summarize(Sum::make()->label('Total')->money('CRC')),
            Tables\Columns\TextColumn::make('IVA')
                ->label('IVA')
                ->money('CRC')
                ->searchable()
                ->sortable()
                ->summarize(Sum::make()->label('Total')->money('CRC')),
            Tables\Columns\TextColumn::make('retentions')
                ->label('Retenciones')
                ->money('CRC')
                ->searchable()
                ->sortable()
                ->summarize(Sum::make()->label('Total')->money('CRC')),
            Tables\Columns\TextColumn::make('total_deposited')
                ->label('Depositado')
                ->money('CRC')
                ->summarize(Sum::make()->label('Total')->money('CRC'))
                ->searchable(),
            Tables\Columns\TextColumn::make('description')
                ->label('Descripción')
                ->searchable()
                ->sortable(),


            ])
            
            ->filters([
                //Tables\Filters\TrashedFilter::make()
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Crear ingreso'),
                //Tables\Actions\AssociateAction::make(),
            ])
            ->actions([
                Action::make('view_attachment')
                    ->label('Ver adjunto')
                    ->url(fn ($record) => asset('storage/' . $record->attachment))
                    ->openUrlInNewTab()
                    ->color('warning')
                    ->icon('heroicon-o-eye')
                    ->visible(function ($record) {
                        $record = $record->fresh();
                        $path = is_array($record->attachment) ? ($record->attachment[0] ?? null) : $record->attachment;
                        return $path && Storage::disk('public')->exists($path);
                    }),
                Tables\Actions\EditAction::make(),
                //Tables\Actions\DissociateAction::make(),
                Tables\Actions\DeleteAction::make(),
                //Tables\Actions\ForceDeleteAction::make(),
                //Tables\Actions\RestoreAction::make()
                ActivityLogTimelineTableAction::make('Activities')
                    ->label('Actividad')
                    ->color('info')
                    ->limit(15),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DissociateBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                    //Tables\Actions\RestoreBulkAction::make(),
                    //Tables\Actions\ForceDeleteBulkAction::make(),
                    FilamentExportBulkAction::make('Exportar'),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]));
    }
}
