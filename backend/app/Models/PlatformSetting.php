<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value_json',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'value_json' => 'array',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Helper to get flat commission amount in Rupiah.
     */
    public static function getPlatformCommission(): int
    {
        $setting = static::where('key', 'platform_commission_flat')->first();
        if (!$setting || !isset($setting->value_json['amount'])) {
            return 1000;
        }

        return (int) $setting->value_json['amount'];
    }
}
