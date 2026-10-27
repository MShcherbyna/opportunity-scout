<?php

namespace App\Enums;

enum ThreadsSearchRunOutcome: string
{
    case Completed = 'completed';
    case Partial = 'partial';
    case PausedQuota = 'paused_quota';
    case BudgetReached = 'budget_reached';
    case StoppedDisconnected = 'stopped_disconnected';
}
