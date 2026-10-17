<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatbotSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'title',
        'context_type',
        'context_id',
        'provider',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class, 'session_id')->orderBy('created_at', 'asc');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatbotMessage::class, 'session_id')->orderBy('created_at', 'desc');
    }
}
