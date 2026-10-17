<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id',
        'channel',
        'event_type',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if a specific notification channel and event is enabled for the user.
     * In-app is always enabled by default unless specifically disabled.
     */
    public static function isEnabled(User|string $user, string $channel, string $eventType = 'all'): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        // In-app channel defaults to enabled
        // Check specific event preference first, then fallback to 'all'
        $pref = static::where('user_id', $userId)
            ->where('channel', $channel)
            ->where(function ($q) use ($eventType) {
                $q->where('event_type', $eventType)
                  ->orWhere('event_type', 'all');
            })
            ->orderByRaw("CASE WHEN event_type = ? THEN 1 ELSE 2 END", [$eventType])
            ->first();

        if ($pref) {
            return (bool) $pref->is_enabled;
        }

        // Default: all channels are enabled by default
        return true;
    }
}
