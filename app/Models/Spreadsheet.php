<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Storage;

class Spreadsheet extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'date',
        'period',
        'attachment',
        'project_id',
    ];

    public function project(){
        return $this->belongsTo(Project::class);
    }

    public function payment(): HasMany{
        return $this->hasMany(Payment::class);
    }

    protected static function booted()
    {
        static::updating(function ($spreadsheet) {
            // Check if attachment is being changed or removed
            if ($spreadsheet->isDirty('attachment')) {
                $oldAttachment = $spreadsheet->getOriginal('attachment');
    
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

        static::deleting(function ($spreadsheet) {
            // Delete associated payments when spreadsheet is deleted
            $spreadsheet->payment()->delete();
            
            // Delete the attachment file when spreadsheet is deleted
            $attachment = $spreadsheet->attachment;

            if ($attachment) {
                if (is_array($attachment)) {
                    foreach ($attachment as $filePath) {
                        if (Storage::disk('public')->exists($filePath)) {
                            Storage::disk('public')->delete($filePath);
                        }
                    }
                } else if (is_string($attachment)) {
                    if (Storage::disk('public')->exists($attachment)) {
                        Storage::disk('public')->delete($attachment);
                    }
                }
            }
        });
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
