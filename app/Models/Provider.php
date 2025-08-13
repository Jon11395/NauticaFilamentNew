<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class Provider extends Model
{
    use HasFactory, LogsActivity;


    protected $fillable = [
        'name',
        'phone',
        'email',
        'country_id',
        'state_id',
        'city_id',
    ];

    public function country(){
        return $this->belongsTo(Country::class);
    }

    public function state(){
        return $this->belongsTo(State::class);
    }

    public function city(){
        return $this->belongsTo(City::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Proveedores')
            ->logOnly(['name', 'Telefono', 'email', 'Pais','Estado', 'Ciudad' ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This provider has been {$eventName}");
    }

    public function getNombreAttribute(): string
    {
        return $this->name;
    }

    public function getTelefonoAttribute(): string
    {
        return $this->phone ?? 'sin telefono';
    }


    public function getPaisAttribute(): string
    {
        return $this->country->name;
    }

    public function getEstadoAttribute(): string
    {
        return $this->state->name;
    }

    public function getCiudadAttribute(): string
    {
        return $this->city->name;
    }


}
