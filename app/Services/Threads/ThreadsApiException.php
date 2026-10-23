<?php

namespace App\Services\Threads;

use RuntimeException;

class ThreadsApiException extends RuntimeException
{
    public function __construct(
        public readonly ThreadsErrorKind $kind,
        public readonly ?int $httpStatus = null,
        public readonly ?int $threadsCode = null,
        public readonly ?string $sanitizedBody = null,
    ) {
        parent::__construct('Threads API request failed ('.$kind->value.').');
    }
}
