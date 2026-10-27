<?php

namespace App\Services\Threads;

use DateTimeZone;
use Illuminate\Http\Request;

class ClientTimezone
{
    private const MAX_LENGTH = 64;

    /**
     * Legacy IANA aliases mapped to their canonical identifier. Some PHP
     * builds ship zoneinfo data without the `backward` compatibility links,
     * so these are resolved explicitly instead of relying on
     * `DateTimeZone::listIdentifiers(ALL_WITH_BC)` recognising them.
     *
     * @var array<string, string>
     */
    private const LEGACY_ALIASES = [
        'Europe/Kiev' => 'Europe/Kyiv',
    ];

    /**
     * Resolve the IANA time zone from the `timezone` cookie, falling back to UTC.
     */
    public function fromRequest(Request $request): string
    {
        $value = $request->cookie('timezone');

        if (! is_string($value) || $value === '' || strlen($value) > self::MAX_LENGTH) {
            return 'UTC';
        }

        $value = self::LEGACY_ALIASES[$value] ?? $value;

        if (! in_array($value, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            return 'UTC';
        }

        return $value;
    }
}
