<?php

namespace App\Traits;

use Carbon\Carbon;

trait FormatsTimestamps
{
    /**
     * Format timestamp with Asia/Jakarta timezone.
     */
    public function formatTimestamp(?string $raw, $fallback = null): ?string
    {
        if ($raw) {
            return Carbon::parse($raw, 'Asia/Jakarta')->toIso8601String();
        }

        return $fallback ? Carbon::parse($fallback)->setTimezone('Asia/Jakarta')->toIso8601String() : null;
    }
}
