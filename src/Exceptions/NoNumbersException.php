<?php

namespace VirtualSMS\Exceptions;

/**
 * Out-of-stock / no-numbers-available. The backend has no distinct status
 * code for this today (confirmed gap — see SDK spec §"Error model"): a 503
 * with a body containing "out of stock" / "no numbers" is otherwise
 * indistinguishable from any other 5xx. This SDK sniffs the message body to
 * synthesize this subtype client-side. [UNVERIFIED — backend enhancement
 * needed: a distinct status/code, e.g. 409, would let SDKs drop this sniff.]
 */
class NoNumbersException extends ServerErrorException
{
}
