<?php

namespace App\Enums;

enum ThreadsDisconnectReason: string
{
    case User = 'user';
    case Uninstalled = 'uninstalled';
    case TokenInvalid = 'token_invalid';
    case Expired = 'expired';
}
