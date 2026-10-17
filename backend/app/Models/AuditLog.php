<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'ip_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Helper to safely record administrative audit logs per PRD US-ADMIN-08 & FR-ADMIN-10.
     * Automatically filters sensitive fields (password, otp, secret, token).
     */
    public static function record(
        User|string|null $actor,
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $metadata = null,
        ?string $ip = null
    ): self {
        $actorId = null;
        if ($actor instanceof User) {
            $actorId = $actor->id;
        } elseif (is_string($actor) && \Illuminate\Support\Str::isUuid($actor)) {
            $actorId = $actor;
        }

        // Sanitize metadata to never include secrets, passwords, or OTPs
        $cleanMetadata = [];
        if (is_string($actor) && !\Illuminate\Support\Str::isUuid($actor)) {
            $cleanMetadata['system_actor'] = $actor;
        }
        if ($metadata !== null) {
            $cleanMetadata = array_merge($cleanMetadata, self::sanitizeMetadata($metadata));
        }

        $ipHash = null;
        if (!empty($ip)) {
            $ipHash = hash('sha256', $ip);
        }

        return self::create([
            'actor_id' => $actorId,
            'action' => strtoupper($action),
            'entity_type' => $entityType,
            'entity_id' => $entityId ? (string) $entityId : null,
            'metadata' => $cleanMetadata,
            'ip_hash' => $ipHash,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively sanitize sensitive keys.
     */
    protected static function sanitizeMetadata(array $data): array
    {
        $sensitiveKeys = ['password', 'otp', 'otp_code', 'secret', 'secret_key', 'token', 'key_hash'];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string)$key), $sensitiveKeys)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::sanitizeMetadata($value);
            }
        }

        return $data;
    }
}
