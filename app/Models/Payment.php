<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class Payment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'salary',
        'additionals',
        'rebates',
        'ccss',
        'deposited',
        'description',
        'employee_id',
        'spreadsheet_id'
    ];

    public function spreadsheet(){
        return $this->belongsTo(Spreadsheet::class);
    }

    public function employee(){
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Gastos')
            ->logOnly(['Salario', 'Descripcion', 'Nombre'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This expense has been {$eventName}");
    }

    public function getSalarioAttribute(): string { return $this->salary; }
    public function getDescripcionAttribute(): string { return $this->description ?? ''; }
    public function getNombreAttribute(): string { return $this->employee->name ?? ''; }

}
