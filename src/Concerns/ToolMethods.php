<?php

namespace VirtualSMS\Concerns;

/** Other (1 method): public utility endpoints requiring no API key. */
trait ToolMethods
{
    /** Carrier + line-type lookup for an arbitrary E.164 number. Public, no auth. */
    public function check_number(string $number): array
    {
        return $this->request('GET', '/api/v1/tools/number-check', ['number' => $number], null, false);
    }
}
