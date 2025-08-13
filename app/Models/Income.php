<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class Income extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'bill_number',
        'date',
        'bill_amount',
        'IVA',
        'retentions',
        'description',
        'total_deposited',
        'attachment',
    ];

    public function project(){
        return $this->belongsTo(Project::class);
    }

    protected static function booted()
    {

        static::updating(function ($income) {
            // Check if attachment is being changed or removed
            if ($income->isDirty('attachment')) {
                $oldAttachment = $income->getOriginal('attachment');
    
                if ($oldAttachment) {
                    if (is_array($oldAttachment)) {
                        foreach ($oldAttachment as $filePath) {
                            if (Storage::disk('public')->exists($filePath)) {
                                Storage::disk('public')->delete($filePath);
                            }
                        }
                    } else if (is_string($oldAttachment)) {
                        if (Storage::disk('public')->exists($oldAttachment)) {
                            Storage::disk('public')->delete($oldAttachment);
                        }
                    }
                }
            }
        });

        static::deleting(function ($income) {
            // If 'attachment' is an array of files (multiple attachments)
            $attachments = $income->attachment;

            if (is_array($attachments)) {
                foreach ($attachments as $filePath) {
                    if (Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                }
            } else if (is_string($attachments)) {
                // In case of single file stored as string
                if (Storage::disk('public')->exists($attachments)) {
                    Storage::disk('public')->delete($attachments);
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Ingresos')
            ->logOnly(['Factura', 'Fecha', 'Monto', 'IVA', 'Retenciones', 'Descripcion', 'Depositado', 'Adjunto'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This income has been {$eventName}");
    }

    public function getFacturaAttribute(): string
    {
        return $this->bill_number;
    }

    public function getFechaAttribute(): string
    {
        return $this->date;
    }

    public function getMontoAttribute(): string
    {
        return $this->bill_amount;
    }

    public function getRetencionesAttribute(): string
    {
        return $this->retentions;
    }

    public function getDescripcionAttribute(): string
    {
        return $this->description ?? 'sin descripción';
    }

    public function getDepositadoAttribute(): string
    {
        return $this->total_deposited;
    }

    public function getAdjuntoAttribute(): string
    {
        return $this->attachment ? 'Adjunto' : 'Adjunto eliminado';
    }


}
