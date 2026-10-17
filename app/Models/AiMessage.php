<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'content',
        'role',
        'model',
        'tool_calls',
        'metadata',
    ];

    protected $casts = [
        'tool_calls' => 'json',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}