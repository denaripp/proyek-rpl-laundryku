<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'content',
        'data',
        'read_at',
        'delivered_via',
        'email_sent_at',
        'email_error',
    ];

    protected $casts = [
        'data' => 'json',
        'delivered_via' => 'json',
        'read_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========== SCOPES ==========

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }
}