<?php

namespace App\Filament\Pages;

use App\Models\Project;
use App\Models\Timesheet;
use App\Models\Employee;
use App\Policies\ProjectTimesheetSelectorPolicy;
use App\Filament\Pages\GlobalConfig;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

class ProjectTimesheetSelector extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Administrador de Horas';
    protected static ?string $title = 'Administrador de Horas';
    protected static ?string $navigationGroup = 'Proyectos';
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.project-timesheet-selector';

    public ?int $selectedProjectId = null;
    public string $currentView = 'selector';
    public Carbon $currentPeriodStart;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public array $selectedEmployeesToAdd = [];
    public string $employeeSearch = '';
    public int $modalRefreshKey = 0;
    public ?int $employeeToRemove = null;

    /**
     * Check if the current user can access this page
     * Uses Filament Shield policy to verify view permissions
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_ProjectTimesheetSelector') ?? false;
    }

    /**
     * Initialize the component when it mounts
     * Sets up the default 15-day period (today and previous 14 days)
     * and fills the form with initial data
     */
    public function mount(): void
    {
        // Initialize to current period (today and previous 13 days)
        $this->currentPeriodStart = $this->getInitialPeriodStart();
        $this->startDate = $this->currentPeriodStart->format('Y-m-d');
        $this->endDate = $this->getCurrentPeriodEnd()->format('Y-m-d');
        
        $this->form->fill([
            'selectedProjectId' => $this->selectedProjectId,
        ]);
    }

    /**
     * Define the form schema for project selection
     * Creates a dropdown to select which project to manage timesheets for
     * The form updates the selectedProjectId property when changed
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Selección de Proyecto')
                    ->description('Selecciona un proyecto para gestionar sus horas trabajadas')
                    ->schema([
                        Select::make('selectedProjectId')
                            ->label('Proyecto')
                            ->options(Project::all()->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->handleProjectChange($state);
                            })
                            ->placeholder('Selecciona un proyecto...')
                            ->default($this->selectedProjectId),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            // No header actions needed - everything is inline
        ];
    }

    /**
     * Get the name of the currently selected project
     * Returns null if no project is selected
     */
    public function getProjectName(): ?string
    {
        if (!$this->selectedProjectId) {
            return null;
        }
        
        return Project::find($this->selectedProjectId)?->name;
    }

    /**
     * Handle project selection change from the dropdown
     * Updates the selectedProjectId property and triggers calendar refresh
     */
    public function handleProjectChange($projectId): void
    {
        // Convert to integer and handle empty values
        $projectId = $projectId ? (int) $projectId : null;
        
        if (!$projectId) {
            $this->selectedProjectId = null;
            return;
        }
        
        $this->selectedProjectId = $projectId;
    }

    // Period navigation methods
    /**
     * Navigate to the previous 15-day period
     * Moves the calendar 14 days backward
     */
    public function previousPeriod(): void
    {
        $this->currentPeriodStart = $this->currentPeriodStart->subDays(14);
    }

    /**
     * Navigate to the next 15-day period
     * Moves the calendar 14 days forward
     */
    public function nextPeriod(): void
    {
        $this->currentPeriodStart = $this->currentPeriodStart->addDays(14);
    }

    /**
     * Jump to the current period (today and previous 14 days)
     * Resets the calendar to show the most recent 15-day period
     */
    public function goToCurrentPeriod(): void
    {
        $this->currentPeriodStart = $this->getInitialPeriodStart();
        $this->startDate = $this->currentPeriodStart->format('Y-m-d');
        $this->endDate = $this->getCurrentPeriodEnd()->format('Y-m-d');
    }

    /**
     * Handle start date changes from the date picker
     * Automatically calculates and sets the end date to maintain 15-day range
     */
    public function updatedStartDate($value): void
    {
        if ($value) {
            // Automatically set end date to 14 days after start date
            $startDate = Carbon::parse($value);
            $endDate = $startDate->copy()->addDays(14);
            
            $this->endDate = $endDate->format('Y-m-d');
            $this->currentPeriodStart = $startDate;
        }
    }

    /**
     * Handle end date changes from the date picker
     * Automatically calculates and sets the start date to maintain 15-day range
     */
    public function updatedEndDate($value): void
    {
        if ($value) {
            // Automatically set start date to 14 days before end date
            $endDate = Carbon::parse($value);
            $startDate = $endDate->copy()->subDays(14);
            
            $this->startDate = $startDate->format('Y-m-d');
            $this->currentPeriodStart = $startDate;
        }
    }

    private function validateDateRange($start, $end): void
    {
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        
        // Check if range is exactly 15 days
        if ($startDate->diffInDays($endDate) !== 14) {
            Notification::make()
                ->title('Rango de fechas inválido')
                ->body('El rango de fechas debe ser exactamente 15 días')
                ->danger()
                ->send();
            return;
        }
        
        // Check if start date is after end date
        if ($startDate->gt($endDate)) {
            Notification::make()
                ->title('Rango de fechas inválido')
                ->body('La fecha de inicio debe ser anterior a la fecha de fin')
                ->danger()
                ->send();
            return;
        }
        
        // Update the period
        $this->currentPeriodStart = $startDate;
        $this->startDate = $startDate->format('Y-m-d');
        $this->endDate = $endDate->format('Y-m-d');
    }

    // Period data methods
    public function getCurrentPeriodStart(): Carbon
    {
        return $this->currentPeriodStart;
    }

    public function getCurrentPeriodEnd(): Carbon
    {
        // End date is always 14 days after the start date (15 days total)
        return $this->currentPeriodStart->copy()->addDays(14);
    }

    public function getCurrentPeriodName(): string
    {
        $start = $this->currentPeriodStart;
        $end = $this->getCurrentPeriodEnd();
        
        if ($start->month === $end->month) {
            return $start->format('d') . ' - ' . $end->format('d M Y');
        } else {
            return $start->format('d M') . ' - ' . $end->format('d M Y');
        }
    }

    public function getDaysInPeriod(): int
    {
        return 15; // Always 15 days
    }

    public function getPeriodDays(): array
    {
        $days = [];
        $startDate = $this->currentPeriodStart;
        
        for ($i = 0; $i < 15; $i++) {
            $days[] = $startDate->copy()->addDays($i);
        }
        return $days;
    }

    private function getInitialPeriodStart(): Carbon
    {
        // Start 14 days before today, so today is the 15th day
        return now()->subDays(14);
    }

    private function getCurrentBiweeklyPeriodStart(): Carbon
    {
        $today = now();
        $monday = $today->startOfWeek();
        
        // Calculate which biweekly period we're in
        $weekNumber = $monday->weekOfYear;
        $biweeklyPeriod = intval(($weekNumber - 1) / 2);
        
        // Get the start of the current biweekly period
        $yearStart = $monday->copy()->startOfYear();
        $firstMonday = $yearStart->copy()->startOfWeek();
        
        return $firstMonday->addWeeks($biweeklyPeriod * 2);
    }

    /**
     * Get all employees assigned to the selected project
     * Includes employees from both project_employees table and existing timesheet entries
     * Only returns active employees, ordered by name
     */
    public function getProjectEmployees()
    {
        if (!$this->selectedProjectId) {
            return collect();
        }

        // Get employees assigned to this project through active project_employees assignments
        // Only show employees who have active (non-soft-deleted) project assignments
        return Employee::whereHas('projectAssignments', function ($subQuery) {
                $subQuery->where('project_id', $this->selectedProjectId)
                         ->whereNull('deleted_at');
            })
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    public function isEmployeeSelected(int $employeeId): bool
    {
        return in_array($employeeId, $this->selectedEmployeesToAdd);
    }

    public function toggleEmployeeSelection(int $employeeId): void
    {
        // Ensure selectedEmployeesToAdd is an array
        if (!is_array($this->selectedEmployeesToAdd)) {
            $this->selectedEmployeesToAdd = [];
        }
        
        // Convert all values to integers for consistent comparison
        $this->selectedEmployeesToAdd = array_map('intval', $this->selectedEmployeesToAdd);
        
        if (in_array($employeeId, $this->selectedEmployeesToAdd)) {
            // Remove employee from selection
            $this->selectedEmployeesToAdd = array_values(array_filter($this->selectedEmployeesToAdd, function($id) use ($employeeId) {
                return $id !== $employeeId;
            }));
        } else {
            // Add employee to selection
            $this->selectedEmployeesToAdd[] = $employeeId;
        }
    }

    public function openEmployeeModal(): void
    {
        // Clear any previous selections and search when opening modal
        $this->selectedEmployeesToAdd = [];
        $this->employeeSearch = '';
        
        // Increment refresh key to force checkbox refresh
        $this->modalRefreshKey++;
        
        // Open modal immediately
        $this->dispatch('open-modal', id: 'employee-selection-modal');
    }

    public function removeFromSelection(int $employeeId): void
    {
        $this->selectedEmployeesToAdd = array_filter($this->selectedEmployeesToAdd, function($id) use ($employeeId) {
            return (int) $id !== $employeeId;
        });
    }

    public function addEmployeesToProject(): void
    {
        if (!$this->selectedProjectId || empty($this->selectedEmployeesToAdd)) {
            return;
        }

        $addedCount = 0;
        $restoredCount = 0;
        $alreadyAssignedCount = 0;

        foreach ($this->selectedEmployeesToAdd as $employeeId) {
            $employeeId = (int) $employeeId;

            // Check if employee has a soft-deleted assignment
            $softDeletedAssignment = \DB::table('project_employees')
                ->where('project_id', $this->selectedProjectId)
                ->where('employee_id', $employeeId)
                ->whereNotNull('deleted_at')
                ->first();

            if ($softDeletedAssignment) {
                // Restore the soft-deleted assignment
                \DB::table('project_employees')
                    ->where('project_id', $this->selectedProjectId)
                    ->where('employee_id', $employeeId)
                    ->update([
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
                $restoredCount++;
            } else {
                // Check if employee is already assigned to project (not soft-deleted)
                $exists = \DB::table('project_employees')
                    ->where('project_id', $this->selectedProjectId)
                    ->where('employee_id', $employeeId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$exists) {
                    \DB::table('project_employees')->insert([
                        'project_id' => $this->selectedProjectId,
                        'employee_id' => $employeeId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $addedCount++;
                } else {
                    $alreadyAssignedCount++;
                }
            }
        }

        // Show appropriate notifications
        if ($addedCount > 0) {
            Notification::make()
                ->title('Empleados agregados al proyecto')
                ->body("Se agregaron {$addedCount} empleado(s) nuevo(s) al proyecto")
                ->success()
                ->send();
        }

        if ($restoredCount > 0) {
            Notification::make()
                ->title('Empleados restaurados al proyecto')
                ->body("Se restauraron {$restoredCount} empleado(s) al proyecto con sus horas registradas")
                ->success()
                ->send();
        }

        if ($alreadyAssignedCount > 0) {
            Notification::make()
                ->title('Algunos empleados ya estaban asignados')
                ->body("{$alreadyAssignedCount} empleado(s) ya estaban asignados al proyecto")
                ->warning()
                ->send();
        }

        // Clear the selection and search
        $this->selectedEmployeesToAdd = [];
        $this->employeeSearch = '';
        
        // Increment refresh key to force checkbox refresh
        $this->modalRefreshKey++;
        
        // Close the modal
        $this->dispatch('close-modal', id: 'employee-selection-modal');
    }


    public function removeEmployeeAction(): Action
    {
        return Action::make('removeEmployee')
            ->label('Remover Empleado')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remover Empleado del Proyecto')
            ->modalDescription('¿Estás seguro de que deseas remover este empleado del proyecto? Esta acción eliminará el empleado del proyecto y todos sus horas registrados. Esta acción no se puede deshacer.')
            ->modalSubmitActionLabel('Sí, Remover')
            ->modalCancelActionLabel('Cancelar')
            ->action(function () {
                if ($this->employeeToRemove) {
                    $this->removeEmployeeFromProject($this->employeeToRemove);
                    $this->employeeToRemove = null;
                }
            });
    }

    public function removeEmployeeFromProject(int $employeeId): void
    {
        // Set the employee to remove and open confirmation modal
        $this->employeeToRemove = $employeeId;
        $this->dispatch('open-modal', id: 'remove-employee-confirmation-modal');
    }

    public function getEmployeeToRemoveName(): string
    {
        if (!$this->employeeToRemove) {
            return '';
        }
        
        $employee = Employee::find($this->employeeToRemove);
        return $employee ? $employee->name : 'Empleado desconocido';
    }

    public function confirmRemoveEmployee(int $employeeId): void
    {
        if (!$this->selectedProjectId) {
            Notification::make()
                ->title('Error')
                ->body('No hay proyecto seleccionado')
                ->danger()
                ->send();
            return;
        }

        $removedFromProjectEmployees = false;

        // Soft delete from project_employees table if exists
        $deletedFromProjectEmployees = \DB::table('project_employees')
            ->where('project_id', $this->selectedProjectId)
            ->where('employee_id', $employeeId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        if ($deletedFromProjectEmployees > 0) {
            $removedFromProjectEmployees = true;
        }

        if ($removedFromProjectEmployees) {
            $message = 'El empleado ha sido removido del proyecto. Sus horas registradas se mantienen y se restaurarán si vuelve a agregarse al proyecto.';
            
            Notification::make()
                ->title('Empleado removido del proyecto')
                ->body($message)
                ->success()
                ->send();
                
            // Close the modal and reset the employee to remove
            $this->dispatch('close-modal', id: 'remove-employee-confirmation-modal');
            $this->employeeToRemove = null;
        } else {
            Notification::make()
                ->title('Error')
                ->body('No se encontró el empleado en el proyecto')
                ->danger()
                ->send();
        }
    }

    public function getAvailableEmployees()
    {
        if (!$this->selectedProjectId) {
            return collect();
        }

        // Get employees who are either:
        // 1. Not assigned to this project at all, OR
        // 2. Have soft-deleted assignments (can be restored)
        return Employee::where('active', true)
            ->where(function ($query) {
                $query->whereNotIn('id', function ($subQuery) {
                    $subQuery->select('employee_id')
                        ->from('project_employees')
                        ->where('project_id', $this->selectedProjectId);
                })
                ->orWhereIn('id', function ($subQuery) {
                    $subQuery->select('employee_id')
                        ->from('project_employees')
                        ->where('project_id', $this->selectedProjectId)
                        ->whereNotNull('deleted_at');
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function getFilteredEmployees()
    {
        $employees = $this->getAvailableEmployees();
        
        if (empty($this->employeeSearch)) {
            return $employees;
        }
        
        return $employees->filter(function ($employee) {
            return stripos($employee->name, $this->employeeSearch) !== false;
        });
    }

    public function getTimesheetForEmployeeAndDate(int $employeeId, Carbon $date): ?Timesheet
    {
        if (!$this->selectedProjectId) {
            return null;
        }

        return Timesheet::where('project_id', $this->selectedProjectId)
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date->format('Y-m-d'))
            ->first();
    }

    /**
     * Update or create a timesheet entry for an employee on a specific date
     * Handles regular hours, extra hours, and night work flag
     * Deletes the timesheet if all fields are empty/false
     * Rounds hours to 1 decimal place for precision
     */
    public function updateTimesheet(int $employeeId, string $date, $hours, $extraHours = 0, $nightWork = false): void
    {
        if (!$this->selectedProjectId) {
            return;
        }

        $hours = $hours ? round((float) $hours, 1) : 0;
        $extraHours = $extraHours ? round((float) $extraHours, 1) : 0;
        $nightWork = (bool) $nightWork;

        if ($hours <= 0 && $extraHours <= 0 && !$nightWork) {
            // Delete timesheet only if all fields are empty/false
            Timesheet::where('project_id', $this->selectedProjectId)
                ->where('employee_id', $employeeId)
                ->whereDate('date', $date)
                ->delete();
        } else {
            // Update or create timesheet
            Timesheet::updateOrCreate(
                [
                    'project_id' => $this->selectedProjectId,
                    'employee_id' => $employeeId,
                    'date' => $date,
                ],
                [
                    'hours' => $hours,
                    'extra_hours' => $extraHours,
                    'night_work' => $nightWork,
                ]
            );
        }

        Notification::make()
            ->title('Hora actualizada')
            ->success()
            ->send();
    }

    public function getTotalHoursForPeriod(): float
    {
        if (!$this->selectedProjectId) {
            return 0;
        }

        $startDate = $this->currentPeriodStart;
        $endDate = $this->getCurrentPeriodEnd();
        $assignedEmployeeIds = $this->getProjectEmployees()->pluck('id')->toArray();

        return Timesheet::where('project_id', $this->selectedProjectId)
            ->whereIn('employee_id', $assignedEmployeeIds)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->sum(function ($timesheet) {
                return $timesheet->hours + $timesheet->extra_hours;
            });
    }

    public function getTotalRegularHoursForPeriod(): float
    {
        if (!$this->selectedProjectId) {
            return 0;
        }

        $startDate = $this->currentPeriodStart;
        $endDate = $this->getCurrentPeriodEnd();
        $assignedEmployeeIds = $this->getProjectEmployees()->pluck('id')->toArray();

        return Timesheet::where('project_id', $this->selectedProjectId)
            ->whereIn('employee_id', $assignedEmployeeIds)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->sum('hours');
    }

    public function getTotalExtraHoursForPeriod(): float
    {
        if (!$this->selectedProjectId) {
            return 0;
        }

        $startDate = $this->currentPeriodStart;
        $endDate = $this->getCurrentPeriodEnd();
        $assignedEmployeeIds = $this->getProjectEmployees()->pluck('id')->toArray();

        return Timesheet::where('project_id', $this->selectedProjectId)
            ->whereIn('employee_id', $assignedEmployeeIds)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->sum('extra_hours');
    }

    public function getTotalCostForPeriod(): float
    {
        if (!$this->selectedProjectId) {
            return 0;
        }

        $startDate = $this->currentPeriodStart;
        $endDate = $this->getCurrentPeriodEnd();

        $assignedEmployeeIds = $this->getProjectEmployees()->pluck('id')->toArray();
        
        $timesheets = Timesheet::where('project_id', $this->selectedProjectId)
            ->whereIn('employee_id', $assignedEmployeeIds)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with('employee')
            ->get();

        return $timesheets->sum(function ($timesheet) {
            $employee = $timesheet->employee;
            $hourlySalary = $employee ? $employee->hourly_salary : 0;
            
            $regularCost = $timesheet->hours * $hourlySalary;
            $extraCost = $timesheet->extra_hours * $hourlySalary * 1.5; // 1.5x rate for overtime
            $nightWorkBonus = ($timesheet->night_work ? 1 : 0) * GlobalConfig::getNightWorkBonus();
            
            return $regularCost + $extraCost + $nightWorkBonus;
        });
    }

    /**
     * Calculate totals for a specific employee within the current period
     * Returns hours, costs, and night work statistics
     * Includes regular hours, extra hours (1.5x rate), and night work bonus (₡8,300 per day)
     */
    public function getEmployeeTotalsForPeriod(int $employeeId): array
    {
        if (!$this->selectedProjectId) {
            return [
                'regular_hours' => 0,
                'extra_hours' => 0,
                'night_work_days' => 0,
                'total_hours' => 0
            ];
        }

        $startDate = $this->currentPeriodStart;
        $endDate = $this->getCurrentPeriodEnd();

        $timesheets = Timesheet::where('project_id', $this->selectedProjectId)
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $employee = Employee::find($employeeId);
        $hourlySalary = $employee ? $employee->hourly_salary : 0;
        
        $regularHours = $timesheets->sum('hours');
        $extraHours = $timesheets->sum('extra_hours');
        $nightWorkDays = $timesheets->filter(function ($timesheet) {
            return $timesheet->night_work == 1 || $timesheet->night_work === true;
        })->count();
        
        // Calculate costs
        $regularCost = $regularHours * $hourlySalary;
        $extraCost = $extraHours * $hourlySalary * 1.5; // 1.5x rate for overtime
        $nightWorkBonus = $nightWorkDays * GlobalConfig::getNightWorkBonus();
        $totalCost = $regularCost + $extraCost + $nightWorkBonus;

        return [
            'regular_hours' => $regularHours,
            'extra_hours' => $extraHours,
            'night_work_days' => $nightWorkDays,
            'total_hours' => $regularHours + $extraHours,
            'regular_cost' => $regularCost,
            'extra_cost' => $extraCost,
            'night_work_bonus' => $nightWorkBonus,
            'total_cost' => $totalCost
        ];
    }
}
