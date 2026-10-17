<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreDocument extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'store_documents';

    protected $fillable = [
        'store_id',
        'document_type',
        'storage_key',
        'verification_status',
        'reviewed_by',
    ];

    protected $hidden = [
        'storage_key',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
