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
        'hourly_salary',
        'function',
        'account_number',
        'phone',
        'identification',
        'email',
        'country_id',
        'state_id',
        'city_id'
    ];


    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function projectAssignments()
    {
        return $this->belongsToMany(Project::class, 'project_employees');
    }

    //Logs
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Empleados')
            ->logOnly([
                'Nombre', 
                'Estado', 
                'SalarioPorHora', 
                'Funcion', 
                'CuentaBancaria', 
                'Telefono', 
                'Identificacion', 
                'email', 
                'Pais', 
                'EstadoProvincia', 
                'Ciudad'
            ])
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
    
    public function getSalarioPorHoraAttribute()
    {
        return $this->hourly_salary;
    }
    
    public function getFuncionAttribute()
    {
        return $this->function;
    }
    
    public function getCuentaBancariaAttribute()
    {
        return $this->account_number;
    }
    
    public function getTelefonoAttribute()
    {
        return $this->phone;
    }
    
    public function getIdentificacionAttribute()
    {
        return $this->identification;
    }
    
    public function getPaisAttribute()
    {
        return $this->country ? $this->country->name : null;
    }
    
    public function getEstadoProvinciaAttribute()
    {
        return $this->state ? $this->state->name : null;
    }
    
    public function getCiudadAttribute()
    {
        return $this->city ? $this->city->name : null;
    }

    public function country(){
        return $this->belongsTo(Country::class);
    }

    public function state(){
        return $this->belongsTo(State::class);
    }

    public function city(){
        return $this->belongsTo(City::class);
    }
}
