<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedLoginAttempt extends Model
{
    use HasFactory;

    // Schema: no updated_at
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'attempted_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}