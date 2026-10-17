<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    use HasFactory;

    // Schema: no updated_at, only created_at
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'function_call',
        'function_result',
        'token_usage',
        'created_at',
    ];

    protected $casts = [
        'function_call' => 'array',
        'function_result' => 'array',
        'token_usage' => 'array',
        'created_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }
}