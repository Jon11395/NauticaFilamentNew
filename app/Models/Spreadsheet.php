<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class Spreadsheet extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'date',
    ];

    public function project(){
        return $this->belongsTo(Project::class);
    }

    public function payment(): HasMany{
        return $this->hasMany(Payment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Planilla')
            ->logOnly(['Fecha'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This spreadsheet has been {$eventName}");
    }

    public function getFechaAttribute(): string { return $this->date;}

    
}
