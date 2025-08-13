<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class Contract extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'amount',
    ];

    public function project(){
        return $this->belongsTo(Project::class);
    }

    public function contractexpenses(): HasMany
    {
        return $this->hasMany(ContractExpense::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Contratos')
            ->logOnly(['Nombre', 'Monto'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This contract has been {$eventName}");
    }

    public function getNombreAttribute(): string { return $this->name; }

    public function getMontoAttribute(): string { return $this->amount; }
    
}
