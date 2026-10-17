<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeEvent extends Model
{
    use HasFactory;

    public $timestamps = false; // only created_at (DB default)

    protected $fillable = [
        'dispute_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'note',
        'evidence',
    ];

    protected $casts = [
        'evidence' => 'json',
        'created_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}