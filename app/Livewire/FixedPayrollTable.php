<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Employee;
use App\Models\GlobalConfig;
use Carbon\Carbon;

class FixedPayrollTable extends Component
{
    public $employees;
    public $dateFrom;
    public $dateTo;
    public $projectId;
    
    // Employee totals for real-time calculation
    public $employeeTotals = [];
    
    // Modal and selection properties
    public $showModal = false;
    public $availableEmployees = [];
    public $selectedEmployees = [];
    public $selectedEmployeesToAdd = [];
    public $employeeSearch = '';
    
    protected $listeners = ['updateEmployeeTotal'];

    public function mount($employees = null, $dateFrom = null, $dateTo = null, $projectId = null)
    {
        // Start with empty employees collection
        $this->employees = collect();
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->projectId = $projectId;
        
        // Load available employees for selection
        $this->loadAvailableEmployees();
        
        // Initialize employee totals
        $this->initializeEmployeeTotals();
        
        // Store initial data in session
        session(['fixed_payroll_data_' . $this->projectId => $this->employeeTotals]);
    }
    
    public function loadAvailableEmployees()
    {
        // Get all active employees that are not already in the payroll
        $currentEmployeeIds = $this->employees->pluck('id')->toArray();
        $this->availableEmployees = Employee::where('active', true)
            ->whereNotIn('id', $currentEmployeeIds)
            ->get();
    }
    
    public function getFilteredEmployees()
    {
        if (empty($this->employeeSearch)) {
            return $this->availableEmployees;
        }
        
        return $this->availableEmployees->filter(function ($employee) {
            return stripos($employee->name, $this->employeeSearch) !== false ||
                   stripos($employee->function ?? '', $this->employeeSearch) !== false;
        });
    }
    
    public function openEmployeeModal()
    {
        $this->selectedEmployeesToAdd = [];
        $this->loadAvailableEmployees(); // Refresh the list to exclude already added employees
        $this->dispatch('open-modal', id: 'employee-selection-modal');
    }
    
    public function toggleEmployeeSelection($employeeId)
    {
        if (in_array($employeeId, $this->selectedEmployeesToAdd)) {
            $this->selectedEmployeesToAdd = array_diff($this->selectedEmployeesToAdd, [$employeeId]);
        } else {
            $this->selectedEmployeesToAdd[] = $employeeId;
        }
    }
    
    public function addSelectedEmployees()
    {
        if (!empty($this->selectedEmployeesToAdd)) {
            $newEmployees = Employee::whereIn('id', $this->selectedEmployeesToAdd)->get();
            
            // Add new employees to existing collection
            foreach ($newEmployees as $employee) {
                if (!$this->employees->contains('id', $employee->id)) {
                    $this->employees->push($employee);
                }
            }
            
            // Reinitialize employee totals
            $this->initializeEmployeeTotals();
            
            // Store updated data in session
            session(['fixed_payroll_data_' . $this->projectId => $this->employeeTotals]);
        }
        
        $this->dispatch('close-modal', id: 'employee-selection-modal');
        $this->selectedEmployeesToAdd = [];
    }
    
    public function removeEmployee($employeeId)
    {
        $this->employees = $this->employees->reject(function ($employee) use ($employeeId) {
            return $employee->id == $employeeId;
        });
        
        // Remove from employee totals
        unset($this->employeeTotals[$employeeId]);
        
        // Store updated data in session
        session(['fixed_payroll_data_' . $this->projectId => $this->employeeTotals]);
    }
    
    private function initializeEmployeeTotals()
    {
        foreach ($this->employees as $employee) {
            $employeeId = $employee->id;
            
            // Check if employee data exists in session
            $sessionData = session('fixed_payroll_data_' . $this->projectId, []);
            $existingData = $sessionData[$employeeId] ?? [];
            
            $salarioBase = $existingData['salario_base'] ?? 0;
            $adicionales = $existingData['adicionales'] ?? 0;
            $rebajos = $existingData['rebajos'] ?? 0;
            $ccss = $existingData['ccss'] ?? 0;
            
            $this->employeeTotals[$employeeId] = [
                'salario_base' => $salarioBase,
                'adicionales' => $adicionales,
                'rebajos' => $rebajos,
                'ccss' => $ccss,
                'total_final' => $salarioBase + $adicionales - $rebajos - $ccss
            ];
        }
    }
    
    public function updateEmployeeTotal($employeeId, $field, $value)
    {
        if (isset($this->employeeTotals[$employeeId])) {
            $this->employeeTotals[$employeeId][$field] = floatval($value);
            
            // Recalculate total final
            $salarioBase = floatval($this->employeeTotals[$employeeId]['salario_base'] ?? 0);
            $adicionales = floatval($this->employeeTotals[$employeeId]['adicionales'] ?? 0);
            $rebajos = floatval($this->employeeTotals[$employeeId]['rebajos'] ?? 0);
            $ccss = floatval($this->employeeTotals[$employeeId]['ccss'] ?? 0);
            
            $this->employeeTotals[$employeeId]['total_final'] = $salarioBase + $adicionales - $rebajos - $ccss;
        }
    }
    
    public function updatedEmployeeTotals($value, $key)
    {
        // This method is called automatically when any employeeTotals property is updated
        $parts = explode('.', $key);
        if (count($parts) === 2) {
            $employeeId = $parts[0];
            $field = $parts[1];
            
            if (isset($this->employeeTotals[$employeeId])) {
                // List of fields that can be edited
                $editableFields = ['salario_base', 'adicionales', 'rebajos', 'ccss'];
                
                if (in_array($field, $editableFields)) {
                    // Validate the input value
                    $numericValue = $this->validateNumericInput($value, $field);
                    $this->employeeTotals[$employeeId][$field] = $numericValue;
                    
                    // Recalculate total final
                    $salarioBase = floatval($this->employeeTotals[$employeeId]['salario_base'] ?? 0);
                    $adicionales = floatval($this->employeeTotals[$employeeId]['adicionales'] ?? 0);
                    $rebajos = floatval($this->employeeTotals[$employeeId]['rebajos'] ?? 0);
                    $ccss = floatval($this->employeeTotals[$employeeId]['ccss'] ?? 0);
                    
                    $this->employeeTotals[$employeeId]['total_final'] = $salarioBase + $adicionales - $rebajos - $ccss;
                    
                    // Store in session for other components to access
                    session(['fixed_payroll_data_' . $this->projectId => $this->employeeTotals]);
                    
                    // Force session save
                    session()->save();
                }
            }
        }
    }
    
    private function validateNumericInput($value, $field)
    {
        // Remove any non-numeric characters except decimal point and comma
        $cleanedValue = preg_replace('/[^0-9.,]/', '', $value);
        
        // Convert comma to decimal point for proper parsing
        $cleanedValue = str_replace(',', '.', $cleanedValue);
        
        // If empty, return 0
        if (empty($cleanedValue) || $cleanedValue === '') {
            return 0;
        }
        
        // Convert to float
        $numericValue = floatval($cleanedValue);
        
        // Ensure it's not negative
        if ($numericValue < 0) {
            return 0;
        }
        
        return $numericValue;
    }
    
    public function getGrandTotalsProperty()
    {
        $totals = [
            'total_final' => 0
        ];
        
        foreach ($this->employeeTotals as $employeeTotal) {
            $totals['total_final'] += floatval($employeeTotal['total_final']);
        }
        
        return $totals;
    }
    
    /**
     * Manually save current employee totals to session
     * This can be called when navigating between wizard steps
     */
    public function saveToSession()
    {
        session(['fixed_payroll_data_' . $this->projectId => $this->employeeTotals]);
        session()->save();
    }

    public function render()
    {
        return view('livewire.fixed-payroll-table');
    }
}
