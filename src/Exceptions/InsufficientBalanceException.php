<?php

namespace VirtualSMS\Exceptions;

/** Thrown on HTTP 402: account balance is too low for the requested purchase. */
class InsufficientBalanceException extends VirtualSMSException
{
}
