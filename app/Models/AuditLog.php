<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'actor_role',
        'action',
        'resource_type',
        'resource_id',
        'before_value',
        'after_value',
        'ip_address',
        'user_agent',
        'request_id',
        'metadata',
    ];

    protected $casts = [
        'before_value' => 'json',
        'after_value' => 'json',
        'metadata' => 'json',
        'created_at' => 'datetime',
    ];

    public $timestamps = false; // only created_at

    // ========== RELATIONSHIPS ==========

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ========== SCOPES ==========

    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeResource($query, $type, $id = null)
    {
        $query->where('resource_type', $type);
        if ($id) {
            $query->where('resource_id', $id);
        }
        return $query;
    }
}