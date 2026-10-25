<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use HasFactory;

    public $timestamps = false; // only created_at (DB default)

    protected $fillable = [
        'user_id',
        'conversation_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'tool_rounds',
        'status',
        'flags',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost_usd' => 'decimal:6',
        'tool_rounds' => 'integer',
        'flags' => 'json',
        'created_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class);
    }

    /**
     * Get recent unknown queries for pattern review and taxonomy expansion.
     * Guaranteed to contain only normalized text with PII (phone/email) stripped.
     */
    public static function getUnknownQueriesForReview(int $limit = 50): \Illuminate\Support\Collection
    {
        return static::query()
            ->whereNotNull('flags->unknown_query')
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'user_id', 'created_at', 'flags']);
    }
}