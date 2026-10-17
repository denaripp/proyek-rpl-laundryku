<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ratings';

    protected $fillable = [
        'order_id',
        'customer_id',
        'store_id',
        'score',
        'review',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
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
}
