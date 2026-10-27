<?php

namespace App\Enums;

enum ThreadsDataDeletionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}
