<?php

namespace VirtualSMS\Exceptions;

/**
 * Thrown on HTTP 429: rate limit exceeded. Never auto-retried by this SDK —
 * fighting the server's own rate limiter is wrong. Slow down and retry later.
 */
class RateLimitedException extends VirtualSMSException
{
}
