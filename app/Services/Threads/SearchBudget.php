<?php

namespace App\Services\Threads;

use App\Models\ThreadsSearchRun;
use App\Models\User;

class SearchBudget
{
    public const DAILY_BUDGET = 2000;

    public const THREADS_DAILY_LIMIT = 2200;

    /**
     * Queries sent by the user's runs that started within the last 24 hours.
     */
    public function queriesUsedLast24Hours(User $user): int
    {
        return (int) ThreadsSearchRun::query()
            ->where('user_id', $user->id)
            ->where('started_at', '>=', now()->subHours(24))
            ->sum('queries_sent');
    }

    /**
     * Queries left in the daily budget, never below zero.
     */
    public function remainingQueries(User $user): int
    {
        return max(0, self::DAILY_BUDGET - $this->queriesUsedLast24Hours($user));
    }
}
