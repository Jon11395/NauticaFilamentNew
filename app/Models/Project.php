<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class Project extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'offer_amount',
        'start_date',
        'status',
        'image'
    ];

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function spreadsheets(): HasMany
    {
        return $this->hasMany(Spreadsheet::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Proveedores')
            ->logOnly(['Nombre', 'Oferta', 'Inicio', 'Estado' ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This project has been {$eventName}");
    }

    public function getNombreAttribute(): string
    {
        return $this->name;
    }

    public function getOfertaAttribute(): string
    {
        return $this->offer_amount;
    }

    public function getInicioAttribute(): string
    {
        return $this->start_date;
    }

    public function getEstadoAttribute(): string
    {
        return $this->status;
    }
}
