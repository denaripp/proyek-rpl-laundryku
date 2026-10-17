<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $table = 'user_profiles';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'date_of_birth',
        'identity_data_encrypted',
        'profile_photo_key',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    protected $appends = [
        'avatar_url',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->profile_photo_key)) {
            return null;
        }

        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($this->profile_photo_key);
        $v = $this->updated_at ? $this->updated_at->timestamp : time();

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $v;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
