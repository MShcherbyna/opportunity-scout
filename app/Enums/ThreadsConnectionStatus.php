<?php

namespace App\Enums;

enum ThreadsConnectionStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
}
