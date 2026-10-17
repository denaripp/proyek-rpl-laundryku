<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpChallenge extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'otp_challenges';

    protected $fillable = [
        'id',
        'user_id',
        'channel',
        'purpose',
        'destination_hash',
        'otp_hash',
        'metadata',
        'expires_at',
        'attempts',
        'consumed_at',
    ];

    protected $hidden = [
        'otp_hash',
        'destination_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    public function isConsumed(): bool
    {
        return !is_null($this->consumed_at);
    }
}
