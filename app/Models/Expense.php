<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;


class Expense extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'voucher',
        'date',
        'concept',
        'amount',
        'type',
        'provider_id',
        'project_id',
        'expense_type_id',
        'attachment',
        'temporal',
    ];

    protected $casts = [
        'temporal' => 'boolean',
    ];

    public function project(){
        return $this->belongsTo(Project::class);
    }
    public function provider(){
        return $this->belongsTo(Provider::class);
    }

    public function expenseType()
    {
        return $this->belongsTo(ExpenseType::class);
    }

    protected static function booted()
    {
        static::created(function (Expense $expense) {
            if (! $expense->temporal || ! is_null($expense->project_id)) {
                return;
            }

            $recipients = User::permission('view_any_temporal::expense')->get();

            if ($recipients->isEmpty()) {
                return;
            }

            $amount = number_format((float) $expense->amount, 2, ',', '.');
            $concept = $expense->concept ?: 'Sin concepto';

            Notification::make()
                ->title('Nuevo gasto temporal')
                ->body("{$concept} • ₡{$amount}")
                ->icon('heroicon-o-inbox-stack')
                ->warning()
                ->sendToDatabase($recipients);
        });

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
            ->logOnly(['Comprobante', 'Fecha', 'Concepto', 'Monto', 'Tipo', 'Proveedor', 'TipoGasto', 'Adjunto'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "This expense has been {$eventName}");
    }

    public function getComprobanteAttribute(): string { return $this->voucher; }

    public function getFechaAttribute(): string { return $this->date;}
    
    public function getConceptoAttribute(): string { return $this->concept;}

    public function getMontoAttribute(): string { return $this->amount;}

    public function getTipoAttribute(): string { return $this->type;}
    
    public function getProveedorAttribute(): string { return $this->Provider->name;}

    public function getTipoGastoAttribute(): string { return $this->expenseType?->name ?? '';}

    public function getAdjuntoAttribute(): string { return $this->attachment ? 'Adjunto' : 'Adjunto eliminado';}
}
