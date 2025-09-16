<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use Filament\Forms;
use Filament\Tables;

use Filament\Forms\Form;
use Filament\Tables\Table;

use Filament\Tables\Actions\Action;

use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Resources\ProjectResource;

use Filament\Resources\Pages\ManageRelatedRecords;

use Guava\FilamentNestedResources\Concerns\NestedPage;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;

use Illuminate\Support\HtmlString;


class ManageProjectSpreadsheets extends ManageRelatedRecords
{
    use NestedPage;

    protected static string $resource = ProjectResource::class;

    protected static string $relationship = 'spreadsheets';

    protected static ?string $navigationIcon = 'heroicon-c-document-currency-dollar';

    public function getTitle(): string | Htmlable
    {
        return __('Planillas - '. $this->record->name);
    }


    public static function getNavigationLabel(): string
    {
        return 'Planillas';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->label('Fecha')
                    ->default(now()),
            ]);
        
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Planillas')
            ->description('Lista de planillas')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date($format = 'd F Y')
                    ->label('Fecha')
                    ->searchable()
                    ->sortable(),

            ])
            ->filters([
                Filter::make('date')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until')->default(now()),
                    ])
                    // ...
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                
                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('Creado desde ' . Carbon::parse($data['from'])->toFormattedDateString())
                                ->removeField('from');
                        }
                
                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('Creado hasta ' . Carbon::parse($data['until'])->toFormattedDateString())
                                ->removeField('until');
                        }
                
                        return $indicators;
                    })
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Crear planilla'),
                //Tables\Actions\AssociateAction::make(),
            ])
            ->actions([

                Action::make('Pagos')
                    ->modalHeading('Pagos a empleados')
                    ->icon('heroicon-s-eye')
                    ->color('gray')
                    ->modalContent(function($record){
                        return view('filament.resources.projects.pages.SpreadsheetEmployee', ['record' => $record]);
                    })
                    ->modalSubmitAction(false),
                    

                Tables\Actions\EditAction::make(),
                //Tables\Actions\DissociateAction::make(),
                Tables\Actions\DeleteAction::make(),
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

}
