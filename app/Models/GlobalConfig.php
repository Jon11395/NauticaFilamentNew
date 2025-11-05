<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class GlobalConfig extends Model
{
    use LogsActivity;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Get the activity log options for this model
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'value', 'type', 'description'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('global_config');
    }

    /**
     * Get a configuration value by key
     */
    public static function getValue(string $key, $default = null)
    {
        try {
            $config = static::where('key', $key)->first();
            
            if (!$config) {
                return $default;
            }

            return static::castValue($config->value, $config->type);
        } catch (\Exception $e) {
            // Return default if database query fails (prevents recursive logging)
            return $default;
        }
    }

    /**
     * Set a configuration value
     */
    public static function setValue(string $key, $value, string $type = 'string', ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description,
            ]
        );
    }

    /**
     * Cast value based on type
     */
    private static function castValue($value, string $type)
    {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Get all configurations as an array
     */
    public static function getAllAsArray(): array
    {
        return static::all()->mapWithKeys(function ($config) {
            return [$config->key => static::castValue($config->value, $config->type)];
        })->toArray();
    }
}
