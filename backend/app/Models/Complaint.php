<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'complaints';

    protected $fillable = [
        'order_id',
        'customer_id',
        'store_id',
        'category',
        'description',
        'status',
        'resolution_note',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class, 'complaint_id');
    }
}
