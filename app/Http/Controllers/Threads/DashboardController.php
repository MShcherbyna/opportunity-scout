<?php

namespace App\Http\Controllers\Threads;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThreadsPostResource;
use App\Models\User;
use App\Services\Threads\ClientTimezone;
use App\Services\Threads\SearchBudget;
use App\Services\Threads\SearchStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const ACTIVITY_DAYS = 30;

    /**
     * Show the dashboard.
     */
    public function __invoke(
        Request $request,
        ClientTimezone $clientTimezone,
        SearchBudget $budget,
        SearchStatus $searchStatus,
    ): Response {
        $user = $request->user();
        $timezone = $clientTimezone->fromRequest($request);

        return Inertia::render('Dashboard', [
            'threadsQuota' => function () use ($user, $budget): array {
                $used = $budget->queriesUsedLast24Hours($user);

                return [
                    'usedLast24Hours' => $used,
                    'remaining' => max(0, SearchBudget::DAILY_BUDGET - $used),
                    'dailyBudget' => SearchBudget::DAILY_BUDGET,
                    'threadsDailyLimit' => SearchBudget::THREADS_DAILY_LIMIT,
                ];
            },
            'stats' => fn (): array => [
                'postsFound' => $user->threadsPosts()->count(),
                'foundLast7Days' => $user->threadsPosts()->where('created_at', '>=', now()->subDays(7))->count(),
                'foundToday' => $user->threadsPosts()
                    ->where('created_at', '>=', now($timezone)->startOfDay()->utc())
                    ->count(),
            ],
            'activity' => fn (): array => $this->activity($user, $timezone),
            'latestPosts' => fn (): array => ThreadsPostResource::collection(
                $user->threadsPosts()->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get()
            )->resolve($request),
            'searchStatus' => fn (): array => $searchStatus->for($user),
        ]);
    }

    /**
     * Posts found per day for the last 30 days, oldest first, with days starting at 00:00 in the zone.
     *
     * @return array<int, array{date: string, found: int}>
     */
    private function activity(User $user, string $timezone): array
    {
        $today = now($timezone)->startOfDay();
        $days = [];
        $selects = [];
        $bindings = [];

        for ($index = 0; $index < self::ACTIVITY_DAYS; $index++) {
            $start = $today->copy()->subDays(self::ACTIVITY_DAYS - 1 - $index)->startOfDay();
            $end = $start->copy()->addDay()->startOfDay();
            $days[] = $start->format('Y-m-d');
            $selects[] = "COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS day_{$index}";
            $bindings[] = $start->copy()->utc()->format('Y-m-d H:i:s');
            $bindings[] = $end->copy()->utc()->format('Y-m-d H:i:s');
        }

        $counts = DB::table('threads_posts')
            ->selectRaw(implode(', ', $selects), $bindings)
            ->where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::parse($days[0], $timezone)->startOfDay()->utc())
            ->first();

        return array_map(
            fn (string $date, int $index): array => ['date' => $date, 'found' => (int) $counts->{"day_{$index}"}],
            $days,
            array_keys($days),
        );
    }
}
