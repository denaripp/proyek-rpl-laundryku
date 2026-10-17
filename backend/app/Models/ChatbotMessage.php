<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotMessage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'token_count',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'token_count' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatbotSession::class, 'session_id');
    }
}
