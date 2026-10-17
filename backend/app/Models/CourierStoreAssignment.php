<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierStoreAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'courier_store_assignments';

    protected $fillable = [
        'courier_id',
        'store_id',
        'recruited_by',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(CourierProfile::class, 'courier_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruited_by');
    }
}
