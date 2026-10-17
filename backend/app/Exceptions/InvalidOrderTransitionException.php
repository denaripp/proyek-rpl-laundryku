<?php

namespace App\Exceptions;

use Exception;

class InvalidOrderTransitionException extends Exception
{
    public function __construct(
        string $message = 'Transisi status pesanan tidak valid atau Anda tidak memiliki hak akses.',
        protected string $fromStatus = '',
        protected string $toStatus = '',
        int $code = 409
    ) {
        parent::__construct($message, $code);
    }

    public function getFromStatus(): string
    {
        return $this->fromStatus;
    }

    public function getToStatus(): string
    {
        return $this->toStatus;
    }
}
