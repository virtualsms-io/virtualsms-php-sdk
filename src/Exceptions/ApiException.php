<?php

namespace VirtualSMS\Exceptions;

/** Generic fallback for any other 4xx response not covered by a typed subclass. */
class ApiException extends VirtualSMSException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
