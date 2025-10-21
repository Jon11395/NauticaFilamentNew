<div class="w-full">
    @livewire('payroll-table', [
        'employees' => $employees,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'projectId' => $projectId
    ])
</div>
