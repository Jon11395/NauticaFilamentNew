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
            ->deferLoading()
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
                Tables\Columns\TextColumn::make('additionals')
                    ->label('Adicionales')
                    ->money('CRC')
                    ->searchable()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('CRC')),
                Tables\Columns\TextColumn::make('rebates')
                    ->label('Rebajas')
                    ->money('CRC')
                    ->searchable()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('CRC')),
                Tables\Columns\TextColumn::make('ccss')
                    ->label('CCSS')
                    ->money('CRC')
                    ->searchable()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('CRC')),
                Tables\Columns\TextColumn::make('deposited')
                    ->label('Total Depositado')
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
                    Tables\Actions\Action::make('colilla')
                        ->label('Colilla')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->url(fn (Payment $record) => route('salary-receipt.download', $record->id))
                        ->openUrlInNewTab(),
                    Tables\Actions\DeleteAction::make(),
                    ActivityLogTimelineTableAction::make('Activities')
                        ->label('Actividad')
                        ->color('info')
                        ->limit(15),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('generate_all_colillas')
                        ->label('Generar todas las colillas')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($records) {
                            $this->generateAllColillas($records);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Generar todas las colillas')
                        ->modalDescription('¿Está seguro de que desea generar las colillas para todos los empleados seleccionados?')
                        ->modalSubmitActionLabel('Generar colillas'),
                    Tables\Actions\DeleteBulkAction::make(),
                    FilamentExportBulkAction::make('Exportar'),
                ]),
            ]);
    }


    public function generateAllColillas($records)
    {
        try {
            $generatedCount = 0;
            $errors = [];
            $paymentIds = [];
            
            foreach ($records as $payment) {
                try {
                    $paymentIds[] = $payment->id;
                    $generatedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Error preparando colilla para {$payment->employee->name}: " . $e->getMessage();
                }
            }
            
            // Trigger automatic download
            if ($generatedCount > 0) {
                $downloadUrl = route('salary-receipt.bulk-download', ['ids' => implode(',', $paymentIds)]);
                
                // Use JavaScript to trigger automatic download
                $this->js("window.open('{$downloadUrl}', '_blank');");
                
                \Filament\Notifications\Notification::make()
                    ->title('Descargando colillas')
                    ->body("Se están generando {$generatedCount} colillas. El archivo ZIP se descargará automáticamente.")
                    ->success()
                    ->send();
            }
            
            // Show error notifications if any
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    \Filament\Notifications\Notification::make()
                        ->title('Error al preparar colilla')
                        ->body($error)
                        ->danger()
                        ->send();
                }
            }
            
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error al preparar colillas')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.list-employee-payments');
    }
}
