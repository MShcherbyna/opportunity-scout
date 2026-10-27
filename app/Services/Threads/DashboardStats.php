<?php

namespace App\Services\Threads;

use App\Models\ThreadsPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardStats
{
    public const ACTIVITY_DAYS = 30;

    /**
     * Post counters for the dashboard; "today" starts at 00:00 in the zone.
     *
     * @return array{postsFound: int, foundLast7Days: int, foundToday: int, relevantPosts: int}
     */
    public function stats(User $user, string $timezone): array
    {
        return [
            'postsFound' => $user->threadsPosts()->count(),
            'foundLast7Days' => $user->threadsPosts()->where('created_at', '>=', now()->subDays(7))->count(),
            'foundToday' => $user->threadsPosts()
                ->where('created_at', '>=', now($timezone)->startOfDay()->utc())
                ->count(),
            'relevantPosts' => $user->threadsPosts()->where('is_relevant', true)->count(),
        ];
    }

    /**
     * Posts found per day for the last 30 days, oldest first, with days starting at 00:00 in the zone.
     *
     * @return list<array{date: string, found: int, relevant: int}>
     */
    public function activity(User $user, string $timezone): array
    {
        $today = now($timezone)->startOfDay();
        $days = [];
        $selects = [];
        $bindings = [];

        for ($index = 0; $index < self::ACTIVITY_DAYS; $index++) {
            $start = $today->copy()->subDays(self::ACTIVITY_DAYS - 1 - $index)->startOfDay();
            $end = $start->copy()->addDay()->startOfDay();
            $days[] = $start->format('Y-m-d');
            $selects[] = "COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS found_{$index}";
            $bindings[] = $start->copy()->utc()->format('Y-m-d H:i:s');
            $bindings[] = $end->copy()->utc()->format('Y-m-d H:i:s');
            $selects[] = "COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? AND is_relevant = 1 THEN 1 ELSE 0 END), 0) AS relevant_{$index}";
            $bindings[] = $start->copy()->utc()->format('Y-m-d H:i:s');
            $bindings[] = $end->copy()->utc()->format('Y-m-d H:i:s');
        }

        $counts = DB::table('threads_posts')
            ->selectRaw(implode(', ', $selects), $bindings)
            ->where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::parse($days[0], $timezone)->startOfDay()->utc())
            ->first();

        return array_map(
            fn (string $date, int $index): array => [
                'date' => $date,
                'found' => (int) $counts->{"found_{$index}"},
                'relevant' => (int) $counts->{"relevant_{$index}"},
            ],
            $days,
            array_keys($days),
        );
    }

    /**
     * The five most recently found posts, relevant-marked first among them (AC-118).
     *
     * @return Collection<int, ThreadsPost>
     */
    public function latestPosts(User $user): Collection
    {
        return $user->threadsPosts()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->sortBy([
                ['is_relevant', 'desc'],
                ['created_at', 'desc'],
                ['id', 'desc'],
            ])
            ->values();
    }
}
