<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use Filament\Forms;
use Filament\Tables;
use Filament\Actions;
use App\Models\Project;
use App\Models\Contract;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\ContractExpense;
use Illuminate\Support\HtmlString;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Resources\ProjectResource;
use Filament\Tables\Columns\Summarizers\Sum;
use App\Filament\Resources\ContractResource\Pages;
use Filament\Resources\Pages\ManageRelatedRecords;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Guava\FilamentNestedResources\Concerns\NestedPage;
use Illuminate\Support\Facades\DB;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;


class ManageProjectContracts extends ManageRelatedRecords
{
    use NestedPage;

    protected static string $resource = ProjectResource::class;

    protected static string $relationship = 'contracts';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public function getTitle(): string | Htmlable
    {
        return __('Contratos - '. $this->record->name);
    }


    public static function getNavigationLabel(): string
    {
        return 'Contratos';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->label('Monto')
                    ->prefix('₡')
                    ->default(0)
                    ->required()
                    ->numeric()
                    ->live(true)
                    ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
            ]);
        
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Contratos')
            ->description('Lista de contratos')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('CRC')
                    ->summarize(Sum::make()->label('Total')->money('CRC'))
                    ->searchable(),
                /*Tables\Columns\TextColumn::make('ContractExpenses.total_deposited')
                    ->label('Depositado')
                    ->money('CRC')
                    ->summarize(Sum::make()->label('Total')->money('CRC')),*/
                

            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Crear contrato'),
                //Tables\Actions\AssociateAction::make(),
            ])
            ->actions([

      
                Action::make('Gastos')
                    ->modalHeading('Gastos del contrato')
                    ->icon('heroicon-s-eye')
                    ->color('gray')
                    ->modalContent(function($record){
                        return view('filament.resources.projects.pages.ContractExpense', ['record' => $record]);
                    })
                    ->modalSubmitAction(false)
                    ,

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
