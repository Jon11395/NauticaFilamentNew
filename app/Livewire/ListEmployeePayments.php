<?php

namespace App\Livewire;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use App\Models\Salary;
use App\Models\Payment;
use App\Models\Project;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Livewire\Component;
use App\Models\Contract;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Services\PaymentsForm;
use Tables\Actions\EditAction;
use App\Models\ContractExpense;
use Filament\Actions\DeleteAction;
use function Laravel\Prompts\form;
use Illuminate\Contracts\View\View;
use App\Services\ContractExpenseForm;
use App\Services\ContractExpensesForm;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;

class ListEmployeePayments extends Component implements HasForms, HasTable
{

    use InteractsWithTable, InteractsWithForms;
 
    public Payment $payment;

    public $record;

    public function mount($record){
        $this->record = $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Payment::where('spreadsheet_id', $this->record->id))
            ->heading('Planilla')
            ->description('Fecha de planilla: '.Carbon::parse($this->record->date)->format('d/m/Y'))
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('salary')
                    ->label('Salario')
                    ->money('CRC')
                    ->searchable()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('CRC')),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->sortable(),

            ])
            ->filters([
                // ...
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Pago nuevo')
                    ->model(Payment::class)
                    ->form(PaymentsForm::schema($this->record->id))
            ])
            ->actions([
                    Tables\Actions\EditAction::make()
                        ->form(PaymentsForm::schema($this->record->id)),
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


    public function render()
    {
        return view('livewire.list-employee-payments');
    }
}
