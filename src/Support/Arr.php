<?php

namespace VirtualSMS\Support;

final class Arr
{
    /** PHP 7.4-compatible list check (array_is_list() is PHP 8.1+). */
    public static function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
