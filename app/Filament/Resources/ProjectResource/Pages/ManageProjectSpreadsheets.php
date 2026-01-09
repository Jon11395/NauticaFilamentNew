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
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\View;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;

use Illuminate\Support\HtmlString;
use App\Models\Employee;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;




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


    public function table(Table $table): Table
    {
        return $table
            ->heading('Planillas')
            ->description('Lista de planillas')
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('creator'))
            ->columns([
                
                Tables\Columns\TextColumn::make('period')
                    ->label('Período')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '';
                        
                        // Try to parse the period string and format it
                        // Assuming period might be in format "dd/mm/yyyy - dd/mm/yyyy"
                        if (strpos($state, ' - ') !== false) {
                            $parts = explode(' - ', $state);
                            $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($parts[0]))->locale('es');
                            $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($parts[1]))->locale('es');
                            
                            return $startDate->translatedFormat('M d') . ' - ' . $endDate->translatedFormat('M d, Y');
                        }
                        
                        // If it's a single date, try to parse it
                        try {
                            $date = \Carbon\Carbon::parse($state)->locale('es');
                            return $date->translatedFormat('M d, Y');
                        } catch (\Exception $e) {
                            return $state; // Return original if parsing fails
                        }
                    }),

                Tables\Columns\TextColumn::make('payroll_type')
                    ->label('Tipo de Planilla')
                    ->formatStateUsing(function ($state) {
                        if (!$state) {
                            return 'N/A';
                        }
                        
                        return match ($state) {
                            'fixed' => 'Fija',
                            'hourly' => 'Por Horas',
                            default => $state,
                        };
                    })
                    ->badge()
                    ->searchable()
                    ->color(function ($state) {
                        if (!$state || $state === 'N/A') {
                            return 'gray';
                        }
                        
                        return match ($state) {
                            'Fija', 'fixed' => 'success',
                            'Por Horas', 'hourly' => 'info',
                            default => 'gray',
                        };
                    }),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->searchable()
                    ->sortable()
                    ->default('N/A'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha creación')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '';
                        
                        try {
                            $date = \Carbon\Carbon::parse($state)->locale('es');
                            return $date->translatedFormat('M d, Y');
                        } catch (\Exception $e) {
                            return $state; // Return original if parsing fails
                        }
                    }),

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
                //Tables\Actions\CreateAction::make()->label('Crear planilla'),
                
                Tables\Actions\Action::make('generar_planilla')
                    ->label('Generar planilla')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->modal()
                    ->modalHeading('Generar nueva planilla')
                    ->modalDescription('Complete los pasos para generar una nueva planilla')
                    ->modalWidth('7xl')
                    ->extraModalWindowAttributes([
                        'class' => 'fi-modal-large',
                        'style' => 'max-width: 80rem !important; width: 80rem !important;'
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cancelar')
                    ->extraModalFooterActions([
                        \Filament\Tables\Actions\Action::make('generar_planilla_submit')
                            ->label('Generar planilla')
                            ->color('success')
                            ->icon('heroicon-o-check')
                            ->extraAttributes([
                                'x-data' => '{ 
                                    isLastStep: false,
                                    hasData: false,
                                    checkStep() {
                                        const modal = this.$el.closest(".fi-modal");
                                        if (!modal) {
                                            this.isLastStep = false;
                                            return false;
                                        }
                                        const wizard = modal.querySelector(".fi-wizard");
                                        if (!wizard) {
                                            this.isLastStep = false;
                                            return false;
                                        }
                                        const steps = wizard.querySelectorAll(".fi-wizard-step");
                                        const activeStep = wizard.querySelector(".fi-wizard-step-active, [aria-current=\'step\']");
                                        if (!activeStep || !steps.length) {
                                            this.isLastStep = false;
                                            return false;
                                        }
                                        const activeIndex = Array.from(steps).indexOf(activeStep);
                                        const isLast = activeIndex === steps.length - 1;
                                        this.isLastStep = isLast;
                                        
                                        // Check if there is data in the summary step
                                        if (isLast) {
                                            this.checkData();
                                        } else {
                                            this.hasData = false;
                                        }
                                        
                                        return isLast;
                                    },
                                    checkData() {
                                        const modal = this.$el.closest(".fi-modal");
                                        if (!modal) {
                                            this.hasData = false;
                                            return;
                                        }
                                        
                                        // Check for payroll summary table or employee rows
                                        const summaryStep = modal.querySelector(".fi-wizard-step-active, [aria-current=\'step\']");
                                        if (!summaryStep) {
                                            this.hasData = false;
                                            return;
                                        }
                                        
                                        // Look for employee rows in the summary table
                                        // Check for table rows (excluding header rows)
                                        const employeeRows = summaryStep.querySelectorAll("table tbody tr, .fi-ta-table tbody tr, [wire\\:id*=\'payroll-summary\'] tbody tr");
                                        
                                        // Also check for Livewire component that might have employees
                                        const livewireComponent = summaryStep.querySelector("[wire\\:id*=\'payroll-summary\']");
                                        
                                        // Check if there are any employee rows or if the summary shows data
                                        if (employeeRows && employeeRows.length > 0) {
                                            this.hasData = true;
                                            return;
                                        }
                                        
                                        // Check for "no data" messages
                                        const noDataMessages = summaryStep.querySelectorAll(".fi-ta-empty-state, .empty-state, [class*=\'empty\']");
                                        if (noDataMessages && noDataMessages.length > 0) {
                                            this.hasData = false;
                                            return;
                                        }
                                        
                                        // Check if there are any employee cards or list items
                                        const employeeCards = summaryStep.querySelectorAll(".employee-card, .fi-ta-item, [data-employee-id]");
                                        if (employeeCards && employeeCards.length > 0) {
                                            this.hasData = true;
                                            return;
                                        }
                                        
                                        // For fixed payroll, check if there are any employees in the session
                                        // We can check by looking for any content in the summary that indicates employees
                                        const summaryContent = summaryStep.textContent || "";
                                        const hasEmployeeData = summaryContent.includes("empleado") || 
                                                               summaryContent.includes("Empleado") ||
                                                               summaryContent.includes("Total") ||
                                                               (summaryContent.match(/\\d+/) && !summaryContent.includes("No hay"));
                                        
                                        // More specific check: look for table cells with data (not just headers)
                                        const dataCells = summaryStep.querySelectorAll("td:not(:empty), .fi-ta-cell:not(:empty)");
                                        if (dataCells && dataCells.length > 0) {
                                            this.hasData = true;
                                        } else {
                                            this.hasData = hasEmployeeData;
                                        }
                                    }
                                }',
                                'x-init' => '
                                    this.checkStep();
                                    const modal = this.$el.closest(".fi-modal");
                                    if (modal) {
                                        const observer = new MutationObserver(() => {
                                            this.checkStep();
                                        });
                                        observer.observe(modal, { childList: true, subtree: true, attributes: true, attributeFilter: ["class", "aria-current"] });
                                        setInterval(() => {
                                            this.checkStep();
                                        }, 300);
                                    }
                                ',
                                'x-show' => 'isLastStep',
                                'x-bind:disabled' => '!hasData',
                                'x-bind:class' => '!hasData ? "opacity-50 cursor-not-allowed" : ""',
                                'x-cloak' => '',
                                'style' => 'display: none;',
                            ])
                            ->submit('submit'),
                    ])
                    ->requiresConfirmation(false)
                    ->form([
                        Wizard::make([
                            Step::make('Seleccionar fecha')
                                ->description('Seleccione la fecha de inicio y fin de la planilla')
                                ->schema([
                                    Select::make('payroll_type')
                                        ->label('Tipo de planilla')
                                        ->required()
                                        ->options([
                                            'hourly' => 'Planilla por horas',
                                            'fixed' => 'Planilla fija',
                                        ])
                                        //->default('hourly')
                                        ->columnSpanFull(),
                                    
                                    DatePicker::make('date_from')
                                        ->label('Fecha de inicio')
                                        ->required()
                                        ->default(now()->subDays(14))
                                        ->columnSpan(1),
                                    
                                    DatePicker::make('date_to')
                                        ->label('Fecha de fin')
                                        ->required()
                                        ->default(now())
                                        ->after('date_from')
                                        ->columnSpan(1),
                                ])
                                ->columns(2),
                                
                            Step::make('Empleados')
                                ->description(function (Get $get) {
                                    $payrollType = $get('payroll_type');
                                    if ($payrollType === 'hourly') {
                                        return 'Empleados que trabajaron en el período seleccionado';
                                    } else {
                                        return 'Seleccione los empleados para la planilla fija';
                                    }
                                })
                                ->schema([
                                    Placeholder::make('payroll_type_info')
                                        ->label(function (Get $get) {
                                            $payrollType = $get('payroll_type');
                                            $dateFrom = $get('date_from');
                                            $dateTo = $get('date_to');
                                            
                                            if ($payrollType === 'hourly') {
                                                if ($dateFrom && $dateTo) {
                                                    $fromFormatted = \Carbon\Carbon::parse($dateFrom)->format('d/m/Y');
                                                    $toFormatted = \Carbon\Carbon::parse($dateTo)->format('d/m/Y');
                                                    return "Planilla por horas - Período: {$fromFormatted} - {$toFormatted}";
                                                }
                                                return 'Planilla por horas - Seleccione las fechas en el paso anterior';
                                            } else {
                                                if ($dateFrom && $dateTo) {
                                                    $fromFormatted = \Carbon\Carbon::parse($dateFrom)->format('d/m/Y');
                                                    $toFormatted = \Carbon\Carbon::parse($dateTo)->format('d/m/Y');
                                                    return "Planilla fija - Período: {$fromFormatted} - {$toFormatted}";
                                                }
                                                return 'Planilla fija - Seleccione las fechas en el paso anterior';
                                            }
                                        })
                                        ->content(''),
                                    
                                    // Show different content based on payroll type
                                    View::make('livewire.payroll-table-wrapper')
                                        ->visible(fn (Get $get) => $get('payroll_type') === 'hourly')
                                        ->columnSpanFull()
                                        ->viewData(function (Get $get) {
                                            $dateFrom = $get('date_from');
                                            $dateTo = $get('date_to');
                                            
                                            if (!$dateFrom || !$dateTo) {
                                                return ['employees' => collect(), 'dateFrom' => null, 'dateTo' => null, 'projectId' => $this->record->id];
                                            }
                                            
                                            // Get employees who have timesheets in the selected date range for this project
                                            $employees = Employee::whereHas('timesheets', function ($query) use ($dateFrom, $dateTo) {
                                                $query->where('project_id', $this->record->id)
                                                      ->whereBetween('date', [$dateFrom, $dateTo]);
                                            })
                                            ->with(['timesheets' => function ($query) use ($dateFrom, $dateTo) {
                                                $query->where('project_id', $this->record->id)
                                                      ->whereBetween('date', [$dateFrom, $dateTo]);
                                            }])
                                            ->get();
                                            
                                            return [
                                                'employees' => $employees,
                                                'dateFrom' => $dateFrom,
                                                'dateTo' => $dateTo,
                                                'projectId' => $this->record->id
                                            ];
                                        }),
                                    
                                    // Fixed payroll employees table using Livewire component
                                    View::make('livewire.fixed-payroll-table-wrapper')
                                        ->visible(fn (Get $get) => $get('payroll_type') === 'fixed')
                                        ->columnSpanFull()
                                        ->viewData(function (Get $get) {
                                            $dateFrom = $get('date_from');
                                            $dateTo = $get('date_to');
                                            
                                            return [
                                                'dateFrom' => $dateFrom,
                                                'dateTo' => $dateTo,
                                                'projectId' => $this->record->id,
                                            ];
                                        }),
                                ]),
                                
                            Step::make('Resumen')
                                ->description('Revise y confirme la generación')
                                ->schema([
                                    View::make('livewire.payroll-summary')
                                        ->columnSpanFull()
                                        ->viewData(function (Get $get) {
                                            $dateFrom = $get('date_from');
                                            $dateTo = $get('date_to');
                                            $projectId = $this->record->id;
                                            $payrollType = $get('payroll_type');
                                            
                                            if (!$dateFrom || !$dateTo) {
                                                return [
                                                    'dateFrom' => null,
                                                    'dateTo' => null,
                                                    'projectId' => $projectId,
                                                    'payrollType' => $payrollType,
                                                    'employees' => collect(),
                                                    'totals' => []
                                                ];
                                            }
                                            
                                            if ($payrollType === 'hourly') {
                                                // Hourly payroll logic
                                                $employees = Employee::whereHas('timesheets', function ($query) use ($dateFrom, $dateTo, $projectId) {
                                                    $query->where('project_id', $projectId)
                                                          ->whereBetween('date', [$dateFrom, $dateTo]);
                                                })
                                                ->with(['timesheets' => function ($query) use ($dateFrom, $dateTo, $projectId) {
                                                    $query->where('project_id', $projectId)
                                                          ->whereBetween('date', [$dateFrom, $dateTo]);
                                                }])
                                                ->get();

                                                $payrollData = session('payroll_data_' . $projectId, []);

                                                $employeesData = $employees->map(function ($employee) use ($payrollData) {
                                                    $employeeHours = $employee->timesheets->sum('hours');
                                                    $employeeExtraHours = $employee->timesheets->sum('extra_hours');
                                                    $employeeNightDays = $employee->timesheets->where('night_work', true)->count();
                                                    $hourlyRate = $employee->hourly_salary ?? 0;
                                                    $nightWorkBonus = \App\Models\GlobalConfig::getValue('night_work_bonus', 0);
                                                    
                                                    $salarioBase = ($employeeHours * $hourlyRate) + 
                                                                 ($employeeExtraHours * $hourlyRate * 1.5) + 
                                                                 ($employeeNightDays * $nightWorkBonus);
                                                    
                                                    $adicionales = $payrollData[$employee->id]['adicionales'] ?? 0;
                                                    $rebajas = $payrollData[$employee->id]['rebajos'] ?? 0;
                                                    $ccss = $payrollData[$employee->id]['ccss'] ?? 0;
                                                    
                                                    $salarioTotal = $salarioBase + $adicionales - $rebajas - $ccss;

                                                    return [
                                                        'id' => $employee->id,
                                                        'name' => $employee->name,
                                                        'hours' => $employeeHours,
                                                        'extra_hours' => $employeeExtraHours,
                                                        'night_days' => $employeeNightDays,
                                                        'hourly_rate' => $hourlyRate,
                                                        'salario_base' => $salarioBase,
                                                        'adicionales' => $adicionales,
                                                        'rebajos' => $rebajas,
                                                        'ccss' => $ccss,
                                                        'salario_total' => $salarioTotal,
                                                    ];
                                                });
                                            } else {
                                                // Fixed payroll logic
                                                $fixedPayrollData = session('fixed_payroll_data_' . $projectId, []);
                                                $employeesData = collect();
                                                
                                                if (!empty($fixedPayrollData)) {
                                                    // Get employee details for the IDs in the session data
                                                    $employeeIds = array_keys($fixedPayrollData);
                                                    $employees = Employee::whereIn('id', $employeeIds)->get();
                                                    
                                                    $employeesData = $employees->map(function ($employee) use ($fixedPayrollData) {
                                                        $employeeData = $fixedPayrollData[$employee->id] ?? [];
                                                        $salary = $employeeData['salario_base'] ?? 0;
                                                        $adicionales = $employeeData['adicionales'] ?? 0;
                                                        $rebajos = $employeeData['rebajos'] ?? 0;
                                                        $ccss = $employeeData['ccss'] ?? 0;
                                                        $salarioTotal = $salary + $adicionales - $rebajos - $ccss;

                                                        return [
                                                            'id' => $employee->id,
                                                            'name' => $employee->name,
                                                            'salario_base' => $salary,
                                                            'adicionales' => $adicionales,
                                                            'rebajos' => $rebajos,
                                                            'ccss' => $ccss,
                                                            'salario_total' => $salarioTotal,
                                                        ];
                                                    });
                                                }
                                            }

                                            $totals = [
                                                'total_salario_base' => $employeesData->sum('salario_base'),
                                                'total_salario_total' => $employeesData->sum('salario_total'),
                                            ];
                                            
                                            return [
                                                'dateFrom' => $dateFrom,
                                                'dateTo' => $dateTo,
                                                'projectId' => $projectId,
                                                'payrollType' => $payrollType, // Pass payroll type explicitly
                                                'employees' => $employeesData,
                                                'totals' => $totals
                                            ];
                                        }),
                                ]),
                        ])
                        ->submitAction(
                            \Filament\Forms\Components\Actions\Action::make('submit')
                                ->label('Generar planilla')
                                ->color('success')
                                ->icon('heroicon-o-check')
                                ->submit('submit')
                        )
                    ])
                    ->action(function (array $data) {
                        try {
                            // Validate that there is data before proceeding
                            $payrollType = $data['payroll_type'] ?? null;
                            $dateFrom = $data['date_from'] ?? null;
                            $dateTo = $data['date_to'] ?? null;
                            
                            if (!$dateFrom || !$dateTo) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Datos incompletos')
                                    ->body('Por favor, complete las fechas de inicio y fin.')
                                    ->danger()
                                    ->send();
                                return;
                            }
                            
                            if ($payrollType === 'hourly') {
                                // Check if there are employees with timesheets
                                $employeeCount = Employee::whereHas('timesheets', function ($query) use ($dateFrom, $dateTo) {
                                    $query->where('project_id', $this->record->id)
                                          ->whereBetween('date', [$dateFrom, $dateTo]);
                                })->count();
                                
                                if ($employeeCount === 0) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('No hay datos')
                                        ->body('No hay empleados con horas trabajadas en el período seleccionado.')
                                        ->warning()
                                        ->send();
                                    return;
                                }
                            } else {
                                // Check if there are employees in fixed payroll session data
                                $fixedPayrollData = session('fixed_payroll_data_' . $this->record->id, []);
                                
                                if (empty($fixedPayrollData)) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('No hay datos')
                                        ->body('No hay empleados agregados a la planilla fija.')
                                        ->warning()
                                        ->send();
                                    return;
                                }
                            }
                            
                            $this->handlePayrollGeneration($data);
                            
                            // Close the modal and refresh the table
                            $this->dispatch('close-modal', id: 'generar_planilla');
                            $this->dispatch('refresh-table');
                            
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error al procesar la solicitud')
                                ->body('Ocurrió un error inesperado. Por favor, intente nuevamente.')
                                ->danger()
                                ->send();
                        }
                    }),

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

                Action::make('Pagos')
                    ->modalHeading('Pagos a empleados')
                    ->icon('heroicon-s-eye')
                    ->color('gray')
                    ->modalWidth('7xl')
                    ->extraModalWindowAttributes([
                        'class' => 'fi-modal-large',
                        'style' => 'max-width: 80rem !important; width: 80rem !important;'
                    ])
                    ->modalContent(function($record){
                        return view('filament.resources.projects.pages.SpreadsheetEmployee', ['record' => $record]);
                    })
                    ->modalSubmitAction(false),
                    

                //Tables\Actions\EditAction::make(),
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


    private function handlePayrollGeneration(array $data)
    {
        try {
            $payrollType = $data['payroll_type'] ?? 'hourly';
            $dateFrom = $data['date_from'];
            $dateTo = $data['date_to'];
            $period = \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') . ' - ' . \Carbon\Carbon::parse($dateTo)->format('d/m/Y');
            
            // Prepare payroll data based on type
            if ($payrollType === 'hourly') {
                // Handle hourly payroll
                $employees = Employee::whereHas('timesheets', function ($query) use ($dateFrom, $dateTo) {
                    $query->where('project_id', $this->record->id)
                          ->whereBetween('date', [$dateFrom, $dateTo]);
                })
                ->with(['timesheets' => function ($query) use ($dateFrom, $dateTo) {
                    $query->where('project_id', $this->record->id)
                          ->whereBetween('date', [$dateFrom, $dateTo]);
                }])
                ->get();
                
                $payrollData = session('payroll_data_' . $this->record->id, []);
                
                $employeesData = $employees->map(function ($employee) use ($payrollData) {
                    $employeeHours = $employee->timesheets->sum('hours');
                    $employeeExtraHours = $employee->timesheets->sum('extra_hours');
                    $employeeNightDays = $employee->timesheets->where('night_work', true)->count();
                    $hourlyRate = $employee->hourly_salary ?? 0;
                    $nightWorkBonus = \App\Models\GlobalConfig::getValue('night_work_bonus', 0);
                    
                    $salarioBase = ($employeeHours * $hourlyRate) + 
                                 ($employeeExtraHours * $hourlyRate * 1.5) + 
                                 ($employeeNightDays * $nightWorkBonus);
                    
                    $adicionales = $payrollData[$employee->id]['adicionales'] ?? 0;
                    $rebajas = $payrollData[$employee->id]['rebajos'] ?? 0;
                    $ccss = $payrollData[$employee->id]['ccss'] ?? 0;
                    
                    $salarioTotal = $salarioBase + $adicionales - $rebajas - $ccss;

                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'hours' => $employeeHours,
                        'extra_hours' => $employeeExtraHours,
                        'night_days' => $employeeNightDays,
                        'hourly_rate' => $hourlyRate,
                        'salario_base' => $salarioBase,
                        'adicionales' => $adicionales,
                        'rebajos' => $rebajas,
                        'ccss' => $ccss,
                        'salario_total' => $salarioTotal,
                    ];
                });
                
                $totalSalarioTotal = $employeesData->sum('salario_total');
                $employeeCount = $employees->count();
                
            } else {
                // Handle fixed payroll
                $fixedPayrollData = session('fixed_payroll_data_' . $this->record->id, []);
                
                if (!empty($fixedPayrollData)) {
                    $employeeIds = array_keys($fixedPayrollData);
                    $employees = Employee::whereIn('id', $employeeIds)->get();
                    
                    $employeesData = $employees->map(function ($employee) use ($fixedPayrollData) {
                        $employeeData = $fixedPayrollData[$employee->id] ?? [];
                        $salary = $employeeData['salario_base'] ?? 0;
                        $adicionales = $employeeData['adicionales'] ?? 0;
                        $rebajos = $employeeData['rebajos'] ?? 0;
                        $ccss = $employeeData['ccss'] ?? 0;
                        $salarioTotal = $salary + $adicionales - $rebajos - $ccss;

                        return [
                            'id' => $employee->id,
                            'name' => $employee->name,
                            'hours' => 0,
                            'extra_hours' => 0,
                            'night_days' => 0,
                            'hourly_rate' => 0,
                            'salario_base' => $salary,
                            'adicionales' => $adicionales,
                            'rebajos' => $rebajos,
                            'ccss' => $ccss,
                            'salario_total' => $salarioTotal,
                        ];
                    });
                    
                    $totalSalarioTotal = $employeesData->sum('salario_total');
                    $employeeCount = $employees->count();
                } else {
                    $employeesData = collect();
                    $totalSalarioTotal = 0;
                    $employeeCount = 0;
                }
            }
            
            // Generate PDF
            $pdfPath = $this->generatePayrollPDF($employeesData, $period, $payrollType);
            
            // Create the spreadsheet record
            $spreadsheet = $this->record->spreadsheets()->create([
                'date' => $dateFrom,
                'period' => $period,
                'attachment' => $pdfPath,
                'payroll_type' => $payrollType,
                'created_by' => Auth::id(),
            ]);
            
            // Create payment records for each employee
            foreach ($employeesData as $employeeData) {
                $description = $payrollType === 'fixed' ? "Pago de planilla fija" : "Pago de planilla por horas";
                
                \App\Models\Payment::create([
                    'salary' => $employeeData['salario_base'],
                    'additionals' => $employeeData['adicionales'],
                    'rebates' => $employeeData['rebajos'],
                    'ccss' => $employeeData['ccss'],
                    'deposited' => $employeeData['salario_total'],
                    'description' => $description,
                    'employee_id' => $employeeData['id'],
                    'spreadsheet_id' => $spreadsheet->id,
                ]);
            }
            
            // Clear session data
            session()->forget('payroll_data_' . $this->record->id);
            session()->forget('fixed_payroll_data_' . $this->record->id);
            session()->forget('fixed_payroll_employees_data');
            
            // Show success notification
            \Filament\Notifications\Notification::make()
                ->title('Planilla generada exitosamente')
                ->body("Se generó la planilla para {$employeeCount} empleados con un total de ₡" . number_format($totalSalarioTotal, 2) . ". Se crearon {$employeeCount} registros de pago asociados.")
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            
            // Show error notification
            \Filament\Notifications\Notification::make()
                ->title('Error al generar planilla')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function generatePayrollPDF($employeesData, $period, $payrollType)
    {
        try {
            // Create a unique filename
            $filename = 'planilla_' . strtolower($payrollType) . '_' . $this->record->id . '_' . now()->format('Y_m_d_H_i_s') . '.pdf';
            $filePath = 'spreadsheets/' . $filename;
            
            // Generate PDF content (you can customize this based on your PDF library)
            $pdfContent = $this->generatePDFContent($employeesData, $period, $payrollType);
            
            // Save PDF to storage/app/public/
            \Storage::disk('public')->put($filePath, $pdfContent);
            
            return $filePath;
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    private function generatePDFContent($employeesData, $period, $payrollType)
    {
        try {
            // Generate PDF using Barryvdh DomPDF facade
            $pdf = Pdf::loadView('pdf.payroll', [
                'employees' => $employeesData,
                'period' => $period,
                'payrollType' => $payrollType,
                'project' => $this->record,
                'recordImage' => $this->getLogo(),
                'totals' => [
                    'total_salario' => $employeesData->sum('salario_total'),
                    'total_employees' => $employeesData->count(),
                ]
            ]);
            
            
            $output = $pdf->output();
            
            return $output;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function getLogo()
    {
        $imagePath = public_path('images/Logotipo_Editable .png');
        if (!file_exists($imagePath)) {
            // Fallback to other logo files
            $fallbackPaths = [
                public_path('images/logo.png'),
                public_path('images/logo1.png'),
                public_path('images/logo-colorpalette.png')
            ];
            
            foreach ($fallbackPaths as $path) {
                if (file_exists($path)) {
                    $imagePath = $path;
                    break;
                }
            }
        }

        if (!file_exists($imagePath)) {
            return null;
        }

        $type = pathinfo($imagePath, PATHINFO_EXTENSION);
        $data = file_get_contents($imagePath);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}
