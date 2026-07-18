<?php

namespace VirtualSMS\Exceptions;

/**
 * Base exception for every error this SDK throws. Catch this to handle any
 * VirtualSMS API or transport failure generically, or catch one of the
 * typed subclasses below for status-code-specific handling.
 */
class VirtualSMSException extends \RuntimeException
{
}
