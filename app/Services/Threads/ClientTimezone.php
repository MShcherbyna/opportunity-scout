<?php

namespace App\Services\Threads;

use DateTimeZone;
use Illuminate\Http\Request;

class ClientTimezone
{
    private const MAX_LENGTH = 64;

    /**
     * Resolve the IANA time zone from the `timezone` cookie, falling back to UTC.
     */
    public function fromRequest(Request $request): string
    {
        $value = $request->cookie('timezone');

        if (! is_string($value) || $value === '' || strlen($value) > self::MAX_LENGTH) {
            return 'UTC';
        }

        if (! in_array($value, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            return 'UTC';
        }

        return $value;
    }
}
