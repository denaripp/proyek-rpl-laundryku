<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'document_type',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'is_verified',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}