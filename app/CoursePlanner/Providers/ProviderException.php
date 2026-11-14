<?php

namespace App\CoursePlanner\Providers;

class ProviderException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 503)
    {
        parent::__construct($message);
    }
}
