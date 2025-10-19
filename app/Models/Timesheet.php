<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Timesheet extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'project_id',
        'employee_id',
        'date',
        'hours',
        'extra_hours',
        'night_work',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'night_work' => 'boolean',
        'hours' => 'decimal:2',
        'extra_hours' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Timesheets')
            ->logOnly([
                'Proyecto',
                'Empleado',
                'Fecha',
                'Horas',
                'HorasExtra',
                'TrabajoNocturno',
                'Notas'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This timesheet has been {$eventName}");
    }

    public function getProyectoAttribute(): string
    {
        return $this->project ? $this->project->name : 'N/A';
    }

    public function getEmpleadoAttribute(): string
    {
        return $this->employee ? $this->employee->name : 'N/A';
    }

    public function getFechaAttribute(): string
    {
        return $this->date ? $this->date->format('Y-m-d') : 'N/A';
    }

    public function getHorasAttribute(): string
    {
        return $this->hours ?? '0';
    }

    public function getHorasExtraAttribute(): string
    {
        return $this->extra_hours ?? '0';
    }

    public function getTrabajoNocturnoAttribute(): string
    {
        return $this->night_work ? 'Sí' : 'No';
    }

    public function getNotasAttribute(): string
    {
        return $this->notes ?? '';
    }

    // Calculate total hours including extra hours
    public function getTotalHoursAttribute(): float
    {
        return (float) $this->hours + (float) $this->extra_hours;
    }

    // Calculate total cost based on employee hourly salary
    public function getTotalCostAttribute(): float
    {
        if (!$this->employee || !$this->employee->hourly_salary) {
            return 0;
        }

        $regularCost = (float) $this->hours * (float) $this->employee->hourly_salary;
        $extraCost = (float) $this->extra_hours * (float) $this->employee->hourly_salary * 1.5; // 50% extra for overtime
        
        return $regularCost + $extraCost;
    }
}
