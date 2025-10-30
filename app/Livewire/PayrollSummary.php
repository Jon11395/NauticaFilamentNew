<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Employee;
use App\Models\GlobalConfig;

class PayrollSummary extends Component
{
    public $dateFrom;
    public $dateTo;
    public $projectId;
    public $payrollType = 'hourly';
    public $employees = [];
    public $totals = [];

    public function mount($dateFrom = null, $dateTo = null, $projectId = null, $payrollType = 'hourly', $employees = [], $totals = [])
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->projectId = $projectId;
        $this->payrollType = $payrollType;
        $this->employees = collect($employees);
        $this->totals = $totals;
    }

    public function updated($property)
    {
        if (in_array($property, ['dateFrom', 'dateTo', 'projectId'])) {
            $this->loadData();
        }
    }

    public function loadData()
    {
        if (!$this->dateFrom || !$this->dateTo || !$this->projectId) {
            $this->employees = collect();
            $this->totals = [];
            return;
        }

        // If employees data is already loaded (from wizard), don't reload it
        if ($this->employees->isNotEmpty()) {
            return;
        }

        // Get employees who have timesheets in the selected date range for this project
        $employees = Employee::whereHas('timesheets', function ($query) {
            $query->where('project_id', $this->projectId)
                  ->whereBetween('date', [$this->dateFrom, $this->dateTo]);
        })
        ->with(['timesheets' => function ($query) {
            $query->where('project_id', $this->projectId)
                  ->whereBetween('date', [$this->dateFrom, $this->dateTo]);
        }])
        ->get();

        $this->employees = $employees->map(function ($employee) {
            $employeeHours = $employee->timesheets->sum('hours');
            $employeeExtraHours = $employee->timesheets->sum('extra_hours');
            $employeeNightDays = $employee->timesheets->where('night_work', true)->count();
            $hourlyRate = $employee->hourly_salary ?? 0;
            $nightWorkBonus = GlobalConfig::getValue('night_work_bonus', 0);
            
            // Calculate base salary
            $salarioBase = ($employeeHours * $hourlyRate) + 
                         ($employeeExtraHours * $hourlyRate * 1.5) + 
                         ($employeeNightDays * $nightWorkBonus);
            
            // Try to get data from PayrollTable component's session
            $adicionales = 0;
            $rebajas = 0;
            $ccss = 0;
            
            // Check if there's session data from PayrollTable
            $payrollData = session('payroll_data_' . $this->projectId, []);
            if (isset($payrollData[$employee->id])) {
                $adicionales = $payrollData[$employee->id]['adicionales'] ?? 0;
                $rebajas = $payrollData[$employee->id]['rebajos'] ?? 0;
                $ccss = $payrollData[$employee->id]['ccss'] ?? 0;
            }
            
            // Total salary after additions and deductions
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

        // Calculate totals
        $this->totals = [
            'total_hours' => $this->employees->sum('hours'),
            'total_extra_hours' => $this->employees->sum('extra_hours'),
            'total_night_days' => $this->employees->sum('night_days'),
            'total_salario_base' => $this->employees->sum('salario_base'),
            'total_adicionales' => $this->employees->sum('adicionales'),
            'total_rebajas' => $this->employees->sum('rebajos'),
            'total_ccss' => $this->employees->sum('ccss'),
            'total_salario_total' => $this->employees->sum('salario_total'),
        ];
    }

    public function render()
    {
        return view('livewire.payroll-summary');
    }
}