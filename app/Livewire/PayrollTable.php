<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Employee;
use Carbon\Carbon;

class PayrollTable extends Component
{
    public $employees;
    public $dateFrom;
    public $dateTo;
    public $projectId;
    
    // Employee totals for real-time calculation
    public $employeeTotals = [];
    
    protected $listeners = ['updateEmployeeTotal'];

    public function mount($employees, $dateFrom = null, $dateTo = null, $projectId = null)
    {
        $this->employees = $employees;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->projectId = $projectId;
        
        // Initialize employee totals
        $this->initializeEmployeeTotals();
        
        // Store initial data in session
        session(['payroll_data_' . $this->projectId => $this->employeeTotals]);
    }
    
    private function initializeEmployeeTotals()
    {
        foreach ($this->employees as $employee) {
            $totalHoursEmp = $employee->timesheets->sum('hours');
            $totalExtraHoursEmp = $employee->timesheets->sum('extra_hours');
            $totalNightDaysEmp = $employee->timesheets->where('night_work', true)->count();
            $hourlyRate = $employee->hourly_salary ?? 0;
            $nightWorkBonus = \App\Models\GlobalConfig::getValue('night_work_bonus', 0);
            $salarioBase = ($totalHoursEmp * $hourlyRate) + ($totalExtraHoursEmp * $hourlyRate * 1.5) + ($totalNightDaysEmp * $nightWorkBonus);
            
            $this->employeeTotals[$employee->id] = [
                'total_hours' => $totalHoursEmp,
                'total_extra_hours' => $totalExtraHoursEmp,
                'total_night_days' => $totalNightDaysEmp,
                'hourly_rate' => $hourlyRate,
                'salario_base' => $salarioBase,
                'adicionales' => 0,
                'rebajos' => 0,
                'ccss' => 0,
                'total_final' => $salarioBase
            ];
        }
    }
    
    public function updateEmployeeTotal($employeeId, $field, $value)
    {
        if (isset($this->employeeTotals[$employeeId])) {
            $this->employeeTotals[$employeeId][$field] = floatval($value);
            
            // Recalculate total final
            $salarioBase = floatval($this->employeeTotals[$employeeId]['salario_base']);
            $adicionales = floatval($this->employeeTotals[$employeeId]['adicionales']);
            $rebajos = floatval($this->employeeTotals[$employeeId]['rebajos']);
            $ccss = floatval($this->employeeTotals[$employeeId]['ccss']);
            
            $this->employeeTotals[$employeeId]['total_final'] = ($salarioBase + $adicionales - $rebajos) - $ccss;
        }
    }
    
    public function updatedEmployeeTotals($value, $key)
    {
        // This method is called automatically when any employeeTotals property is updated
        $parts = explode('.', $key);
        if (count($parts) === 2) {
            $employeeId = $parts[0];
            $field = $parts[1];
            
            if (isset($this->employeeTotals[$employeeId]) && in_array($field, ['adicionales', 'rebajos', 'ccss'])) {
                // Validate the input value
                $numericValue = $this->validateNumericInput($value, $field);
                $this->employeeTotals[$employeeId][$field] = $numericValue;
                
                // Recalculate total final
                $salarioBase = floatval($this->employeeTotals[$employeeId]['salario_base']);
                $adicionales = floatval($this->employeeTotals[$employeeId]['adicionales']);
                $rebajos = floatval($this->employeeTotals[$employeeId]['rebajos']);
                $ccss = floatval($this->employeeTotals[$employeeId]['ccss']);
                
                $this->employeeTotals[$employeeId]['total_final'] = ($salarioBase + $adicionales - $rebajos) - $ccss;
                
                // Store in session for PayrollSummary component to access
                session(['payroll_data_' . $this->projectId => $this->employeeTotals]);
                
                // Force session save
                session()->save();
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
            'adicionales' => 0,
            'rebajos' => 0,
            'ccss' => 0,
            'total_final' => 0
        ];
        
        foreach ($this->employeeTotals as $employeeTotal) {
            $totals['adicionales'] += floatval($employeeTotal['adicionales']);
            $totals['rebajos'] += floatval($employeeTotal['rebajos']);
            $totals['ccss'] += floatval($employeeTotal['ccss']);
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
        session(['payroll_data_' . $this->projectId => $this->employeeTotals]);
        session()->save();
    }

    public function render()
    {
        return view('livewire.payroll-table');
    }
}