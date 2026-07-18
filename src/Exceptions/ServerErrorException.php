<?php

namespace VirtualSMS\Exceptions;

/**
 * Thrown on any 5xx response. On a GET/HEAD this SDK already retried up to
 * 3 total attempts before giving up and throwing. On a mutating call
 * (POST/PUT/PATCH/DELETE) this was NEVER retried, and the operation may have
 * completed server-side despite the error — check isRetryable() before
 * deciding what to do next.
 */
class ServerErrorException extends VirtualSMSException
{
    /** @var bool True only for GET/HEAD failures — safe to retry. False for mutating calls: verify state first. */
    private bool $retryable;

    public function __construct(string $message, bool $retryable, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->retryable = $retryable;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
