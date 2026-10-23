<?php

namespace App\Services\Threads;

use App\Enums\SearchInterval;
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

    /**
     * The 24-hour quota usage for the dashboard.
     *
     * @return array{usedLast24Hours: int, remaining: int, dailyBudget: int, threadsDailyLimit: int}
     */
    public function usage(User $user): array
    {
        $used = $this->queriesUsedLast24Hours($user);

        return [
            'usedLast24Hours' => $used,
            'remaining' => max(0, self::DAILY_BUDGET - $used),
            'dailyBudget' => self::DAILY_BUDGET,
            'threadsDailyLimit' => self::THREADS_DAILY_LIMIT,
        ];
    }

    /**
     * How the daily budget is spent for an interval and keyword count.
     *
     * @return array{dailyBudget: int, plannedDailyQueries: int, runsPerDay: int, keywordCap: int, keywordCount: int}
     */
    public function plan(SearchInterval $interval, int $keywordCount): array
    {
        return [
            'dailyBudget' => self::DAILY_BUDGET,
            'plannedDailyQueries' => $keywordCount * $interval->runsPerDay(),
            'runsPerDay' => $interval->runsPerDay(),
            'keywordCap' => $interval->keywordCap(),
            'keywordCount' => $keywordCount,
        ];
    }
}
