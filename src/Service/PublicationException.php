<?php

namespace App\Service;

final class PublicationException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null, public readonly string $reason = 'invalid')
    {
        parent::__construct($message, $code, $previous);
    }
}
