<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    // ========== RELATIONSHIPS ==========

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}