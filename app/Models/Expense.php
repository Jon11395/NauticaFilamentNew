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
        'clave',
        'document_type',
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
        'attachment' => 'array',
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
                ->broadcast($recipients)
                ->sendToDatabase($recipients, true);
        });

        static::updating(function ($expense) {
            // Only process if attachment field is explicitly being changed
            // Check both dirty state and if new value is actually different
            if ($expense->isDirty('attachment')) {
                $oldAttachment = $expense->getOriginal('attachment');
                $newAttachment = $expense->attachment;
                
                // Helper to normalize attachment to array of clean paths
                $normalizeAttachment = function($attachment) {
                    $paths = [];
                    if (is_array($attachment)) {
                        $paths = $attachment;
                    } else if (is_string($attachment) && !empty($attachment)) {
                        $decoded = json_decode($attachment, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $paths = $decoded;
                        } else {
                            $paths = [$attachment];
                        }
                    }
                    
                    // Normalize each path
                    return array_map(function($path) {
                        if (!is_string($path) || empty($path)) return null;
                        $cleaned = trim($path, " \t\n\r\0\x0B'\"");
                        $cleaned = ltrim($cleaned, '/');
                        return preg_replace('#^storage/#', '', $cleaned);
                    }, array_filter($paths, fn($p) => !empty($p)));
                };
                
                $oldPathsNormalized = $normalizeAttachment($oldAttachment);
                $newPathsNormalized = $normalizeAttachment($newAttachment);
                
                // Sort both arrays for comparison
                sort($oldPathsNormalized);
                sort($newPathsNormalized);
                
                // Only proceed if attachments are actually different
                if ($oldPathsNormalized !== $newPathsNormalized) {
                    // Only delete files that are not in the new attachment
                    $filesToDelete = array_diff($oldPathsNormalized, $newPathsNormalized);
                    
                    foreach ($filesToDelete as $filePath) {
                        if (empty($filePath) || !is_string($filePath)) {
                            continue;
                        }
                        
                        // Clean the path
                        $cleanPath = ltrim($filePath, '/');
                        $cleanPath = preg_replace('#^storage/#', '', $cleanPath);
                        
                        // Check if this file is used by any other expense before deleting
                        $isUsedByOtherExpense = self::where('id', '!=', $expense->id)
                            ->where(function ($query) use ($cleanPath, $filePath) {
                                $query->where('attachment', 'like', '%' . $cleanPath . '%')
                                    ->orWhere('attachment', 'like', '%' . $filePath . '%')
                                    ->orWhereJsonContains('attachment', $cleanPath)
                                    ->orWhereJsonContains('attachment', $filePath);
                            })
                            ->exists();
                        
                        if (!$isUsedByOtherExpense && Storage::disk('public')->exists($cleanPath)) {
                            \Log::info('Deleting attachment file during update', [
                                'expense_id' => $expense->id,
                                'file_path' => $cleanPath,
                            ]);
                            Storage::disk('public')->delete($cleanPath);
                        }
                    }
                } else {
                    // Attachments are the same (just normalized differently), restore original
                    // This prevents Laravel from treating it as changed
                    $expense->attachment = $oldAttachment;
                }
            }
        });

        static::deleting(function ($expense) {
            // Delete related credit notes (nota_credito) when expense is deleted
            // Credit notes are linked by the 'clave' field (which stores the original invoice's clave)
            if ($expense->clave && $expense->provider_id) {
                $relatedCreditNotes = self::where('document_type', 'nota_credito')
                    ->where('clave', $expense->clave)
                    ->where('provider_id', $expense->provider_id)
                    ->where('id', '!=', $expense->id)
                    ->get();

                foreach ($relatedCreditNotes as $creditNote) {
                    \Log::info('Deleting related credit note', [
                        'expense_id' => $expense->id,
                        'credit_note_id' => $creditNote->id,
                        'clave' => $expense->clave,
                    ]);
                    $creditNote->delete();
                }
            }

            // Delete attachment files, but only if they're not used by other expenses
            $attachments = $expense->attachment;

            // Normalize attachments to array
            $attachmentPaths = [];
            if (is_array($attachments)) {
                $attachmentPaths = $attachments;
            } else if (is_string($attachments) && !empty($attachments)) {
                $decoded = json_decode($attachments, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $attachmentPaths = $decoded;
                } else {
                    $attachmentPaths = [$attachments];
                }
            }

            // Check each attachment before deleting
            foreach ($attachmentPaths as $filePath) {
                if (empty($filePath) || !is_string($filePath)) {
                    continue;
                }

                // Clean the path
                $cleanPath = ltrim($filePath, '/');
                $cleanPath = preg_replace('#^storage/#', '', $cleanPath);

                // Check if this file is used by any other expense
                $isUsedByOtherExpense = self::where('id', '!=', $expense->id)
                    ->where(function ($query) use ($cleanPath, $filePath) {
                        $query->where('attachment', 'like', '%' . $cleanPath . '%')
                            ->orWhere('attachment', 'like', '%' . $filePath . '%')
                            ->orWhereJsonContains('attachment', $cleanPath)
                            ->orWhereJsonContains('attachment', $filePath);
                    })
                    ->exists();

                if (!$isUsedByOtherExpense) {
                    // File is not used by any other expense, safe to delete
                    if (Storage::disk('public')->exists($cleanPath)) {
                        \Log::info('Deleting attachment file', [
                            'expense_id' => $expense->id,
                            'file_path' => $cleanPath,
                        ]);
                        Storage::disk('public')->delete($cleanPath);
                    }
                } else {
                    \Log::info('Skipping deletion - file is used by other expense', [
                        'expense_id' => $expense->id,
                        'file_path' => $cleanPath,
                    ]);
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
