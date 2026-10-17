<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * Get a setting by key, cached.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            if (!$setting) {
                return $default;
            }

            return static::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value and update cache.
     */
    public static function set(string $key, mixed $value, ?string $type = null, ?string $group = null, ?string $description = null): static
    {
        $setting = static::firstOrNew(['key' => $key]);

        if ($type !== null) {
            $setting->type = $type;
        } elseif (!$setting->exists) {
            $setting->type = static::detectType($value);
        }

        if ($group !== null) {
            $setting->group = $group;
        }

        if ($description !== null) {
            $setting->description = $description;
        }

        $setting->value = static::serializeValue($value, $setting->type);
        $setting->save();

        Cache::put("setting:{$key}", static::castValue($setting->value, $setting->type), 3600);

        return $setting;
    }

    /**
     * Determine if a setting exists.
     */
    public static function has(string $key): bool
    {
        return static::where('key', $key)->exists();
    }

    /**
     * Forget cached setting.
     */
    public static function forget(string $key): bool
    {
        return Cache::forget("setting:{$key}");
    }

    /**
     * Cast raw database string to typed PHP value.
     */
    protected static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $value,
            'float', 'double', 'decimal' => (float) $value,
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Serialize PHP value for database storage.
     */
    protected static function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'bool' => $value ? '1' : '0',
            'json', 'array' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Detect type from value.
     */
    protected static function detectType(mixed $value): string
    {
        if (is_bool($value)) return 'boolean';
        if (is_int($value)) return 'integer';
        if (is_float($value)) return 'float';
        if (is_array($value)) return 'json';
        return 'string';
    }
}
