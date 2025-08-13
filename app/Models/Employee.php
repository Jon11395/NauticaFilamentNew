<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class Employee extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'active',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    //Logs
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Empleados')
            ->logOnly(['Nombre', 'Estado'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This employee has been {$eventName}");
    }

    public function getEstadoAttribute(): string
    {
        return $this->active ? 'Activo' : 'Inactivo';
    }
    public function getNombreAttribute(): string
    {
        return $this->name;
    }
}
