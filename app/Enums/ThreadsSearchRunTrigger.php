<?php

namespace App\Enums;

enum ThreadsSearchRunTrigger: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
}
