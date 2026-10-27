<?php

namespace App\Services\Threads;

enum ThreadsErrorKind: string
{
    case TokenInvalid = 'token_invalid';
    case RateLimited = 'rate_limited';
    case Transient = 'transient';
    case Other = 'other';
}
