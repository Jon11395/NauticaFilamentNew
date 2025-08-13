<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class ContractExpense extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'voucher',
        'date',
        'concept',
        'total_solicited',
        'retentions',
        'CCSS',
        'total_deposited',
        'contract_id',
        'attachment',
        
    ];

    public function contract(){
        return $this->belongsTo(Contract::class);
    }

    protected static function booted()
    {

        static::updating(function ($expense) {
            // Check if attachment is being changed or removed
            if ($expense->isDirty('attachment')) {
                $oldAttachment = $expense->getOriginal('attachment');
    
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

        static::deleting(function ($expense) {
            // If 'attachment' is an array of files (multiple attachments)
            $attachments = $expense->attachment;

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
            ->useLogName('Gastos')
            ->logOnly(['Comprobante', 'Fecha', 'Concepto', 'Solicitado', 'Retenciones', 'CCSS', 'Total', 'Adjunto'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This expense has been {$eventName}");
    }

    public function getComprobanteAttribute(): string { return $this->voucher; }
    public function getFechaAttribute(): string { return $this->date; }
    public function getConceptoAttribute(): string { return $this->concept; }
    public function getSolicitadoAttribute(): string { return $this->total_solicited; }
    public function getRetencionesAttribute(): string { return $this->retentions; }
    public function getTotalAttribute(): string { return $this->total_deposited; }
    public function getAdjuntoAttribute(): string { return $this->attachment ? 'Adjunto' : 'Adjunto eliminado';}
}
