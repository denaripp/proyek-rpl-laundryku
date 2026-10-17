<?php

namespace App\Services\Ai;

use Exception;

/**
 * Raised when the AI provider is unreachable, errors, or returns garbage.
 * The assistant service catches this and degrades to a human-CS fallback;
 * core marketplace flows must never be affected.
 */
class AiProviderException extends Exception
{
}