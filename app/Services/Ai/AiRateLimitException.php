<?php

namespace App\Services\Ai;

use Exception;

/**
 * Raised when a user exceeds the per-user rate limit or trips abuse detection.
 */
class AiRateLimitException extends Exception
{
}