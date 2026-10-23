<?php

namespace Tests\Feature\Threads;

use App\Models\ThreadsSearchRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class DashboardThreadsTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    /**
     * SPEC-01 AC-75: queries sent by runs started in the last 24 hours are used; older runs and other users do not count.
     */
    public function test_quota_widget_counts_queries_of_runs_started_in_the_last_24_hours(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $this->makeRun($user, now()->subHour(), 300);
        $this->makeRun($user, now()->subHours(23), 200);
        $this->makeRun($user, now()->subHours(25), 500);
        $this->makeRun(User::factory()->create(), now()->subHour(), 100);
        ThreadsSearchRun::factory()->for($user)->queued()->create();

        $quota = $this->actingAs($user)->get(route('dashboard'))->requiredProp('threadsQuota');

        $this->assertSame([
            'usedLast24Hours' => 500,
            'remaining' => 1500,
            'dailyBudget' => 2000,
            'threadsDailyLimit' => 2200,
        ], $quota);
    }

    /**
     * SPEC-01 AC-75: the remaining queries are never negative.
     */
    public function test_remaining_queries_never_drop_below_zero(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $this->makeRun($user, now()->subHour(), 2100);

        $quota = $this->actingAs($user)->get(route('dashboard'))->requiredProp('threadsQuota');

        $this->assertSame(2100, $quota['usedLast24Hours']);
        $this->assertSame(0, $quota['remaining']);
    }

    /**
     * SPEC-01 AC-77: total, last 7 days and today are counted from the user's found time.
     */
    public function test_stats_count_total_last_seven_days_and_today(): void
    {
        $this->travelTo('2026-10-23 15:00:00');
        $user = User::factory()->create();
        foreach (['2026-10-23 13:00:00', '2026-10-22 15:00:00', '2026-10-17 15:00:00', '2026-10-13 15:00:00', '2026-07-15 15:00:00'] as $foundAt) {
            $this->makePost($user, ['created_at' => $foundAt]);
        }
        $this->makePost(User::factory()->create(), ['created_at' => '2026-10-23 14:00:00']);

        $stats = $this->actingAs($user)->get(route('dashboard'))->requiredProp('stats');

        $this->assertSame(['postsFound' => 5, 'foundLast7Days' => 3, 'foundToday' => 1], $stats);
    }

    /**
     * SPEC-01 AC-77: the series has 30 days ending today, with the number of posts found each day.
     */
    public function test_activity_series_has_thirty_days_ending_today_with_daily_counts(): void
    {
        $this->travelTo('2026-10-23 15:00:00');
        $user = User::factory()->create();
        $this->makePost($user, ['created_at' => '2026-10-23 01:00:00']);
        $this->makePost($user, ['created_at' => '2026-10-23 14:00:00']);
        $this->makePost($user, ['created_at' => '2026-10-21 09:00:00']);

        $activity = $this->actingAs($user)->get(route('dashboard'))->requiredProp('activity');

        $this->assertCount(30, $activity);
        $this->assertSame(['date' => '2026-09-24', 'found' => 0], $activity[0]);
        $this->assertSame(['date' => '2026-10-21', 'found' => 1], $activity[27]);
        $this->assertSame(['date' => '2026-10-22', 'found' => 0], $activity[28]);
        $this->assertSame(['date' => '2026-10-23', 'found' => 2], $activity[29]);
    }

    /**
     * SPEC-01 AC-107, D-27: a valid IANA zone in the `timezone` cookie moves "today" and the series to that zone.
     */
    public function test_today_and_daily_series_use_the_timezone_cookie(): void
    {
        $this->travelTo('2026-10-23 23:30:00');
        $user = User::factory()->create();
        $this->makePost($user, ['created_at' => '2026-10-23 10:00:00']);
        $this->makePost($user, ['created_at' => '2026-10-23 12:00:00']);

        $response = $this->actingAs($user)->withUnencryptedCookie('timezone', 'Pacific/Auckland')->get(route('dashboard'));

        $activity = $response->requiredProp('activity');
        $this->assertSame(1, $response->requiredProp('stats.foundToday'));
        $this->assertSame(['date' => '2026-09-25', 'found' => 0], $activity[0]);
        $this->assertSame(['date' => '2026-10-23', 'found' => 1], $activity[28]);
        $this->assertSame(['date' => '2026-10-24', 'found' => 1], $activity[29]);
    }

    /**
     * SPEC-01 UI-12 (plan decision): legacy aliases such as `Europe/Kiev` are accepted as valid zones.
     */
    public function test_legacy_timezone_alias_in_the_cookie_is_accepted(): void
    {
        $this->travelTo('2026-10-23 22:30:00');
        $user = User::factory()->create();
        $this->makePost($user, ['created_at' => '2026-10-23 20:00:00']);
        $this->makePost($user, ['created_at' => '2026-10-23 22:15:00']);

        $response = $this->actingAs($user)->withUnencryptedCookie('timezone', 'Europe/Kiev')->get(route('dashboard'));

        $activity = $response->requiredProp('activity');
        $this->assertSame(1, $response->requiredProp('stats.foundToday'));
        $this->assertSame(['date' => '2026-10-23', 'found' => 1], $activity[28]);
        $this->assertSame(['date' => '2026-10-24', 'found' => 1], $activity[29]);
    }

    /**
     * SPEC-01 AC-108, UI-12, EC-47, EC-48: a missing, unknown or overlong cookie falls back to UTC without an error.
     */
    #[DataProvider('unusableTimezoneCookies')]
    public function test_missing_or_invalid_timezone_cookie_falls_back_to_utc(?string $cookie): void
    {
        $this->travelTo('2026-10-23 23:30:00');
        $user = User::factory()->create();
        $this->makePost($user, ['created_at' => '2026-10-23 10:00:00']);
        $this->makePost($user, ['created_at' => '2026-10-23 12:00:00']);
        $request = $this->actingAs($user);
        if ($cookie !== null) {
            $request = $request->withUnencryptedCookie('timezone', $cookie);
        }

        $response = $request->get(route('dashboard'));

        $response->assertOk();
        $activity = $response->requiredProp('activity');
        $this->assertSame(2, $response->requiredProp('stats.foundToday'));
        $this->assertSame(['date' => '2026-09-24', 'found' => 0], $activity[0]);
        $this->assertSame(['date' => '2026-10-23', 'found' => 2], $activity[29]);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function unusableTimezoneCookies(): array
    {
        return [
            'no cookie' => [null],
            'unknown zone' => ['Mars/Base'],
            '500 characters' => [str_repeat('A', 500)],
        ];
    }

    /**
     * SPEC-01 AC-77: "Latest posts" are the five most recently found, newest found first (not by post time).
     */
    public function test_latest_posts_are_the_five_most_recently_found(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $posts = collect(range(0, 6))->map(fn (int $index) => $this->makePost($user, [
            'created_at' => now()->subMinutes($index),
            'posted_at' => now()->subHours(10)->addMinutes($index),
        ]));

        $latest = $this->actingAs($user)->get(route('dashboard'))->requiredProp('latestPosts');

        $this->assertSame($posts->take(5)->pluck('id')->all(), array_column($latest, 'id'));
        $this->assertEqualsCanonicalizing(
            ['id', 'threadsPostId', 'text', 'permalink', 'username', 'keyword', 'postedAt', 'foundAt'],
            array_keys($latest[0]),
        );
    }

    /**
     * SPEC-01 NFR-6: nothing of another user's posts or runs shows on the dashboard.
     */
    public function test_dashboard_shows_only_the_users_data(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->makePost($other, ['created_at' => now()->subMinute()]);
        $this->makeRun($other, now()->subHour(), 900);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame([], $response->requiredProp('latestPosts'));
        $this->assertSame(['postsFound' => 0, 'foundLast7Days' => 0, 'foundToday' => 0], $response->requiredProp('stats'));
        $this->assertSame(0, $response->requiredProp('threadsQuota.usedLast24Hours'));
        $this->assertSame(0, array_sum(array_column($response->requiredProp('activity'), 'found')));
    }

    /**
     * SPEC-01 AC-111, MC-8: a partial reload of `searchStatus` and `latestPosts` returns only those props.
     */
    public function test_partial_reload_returns_only_search_status_and_latest_posts(): void
    {
        $user = $this->createConnectedUser();
        $this->makePost($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('threadsQuota')
                ->reloadOnly(['searchStatus', 'latestPosts'], fn (Assert $reload) => $reload
                    ->has('searchStatus')
                    ->has('latestPosts', 1)
                    ->missing('threadsQuota')
                    ->missing('stats')
                    ->missing('activity')
                )
            );
    }

    private function makeRun(User $user, \DateTimeInterface $startedAt, int $queriesSent): ThreadsSearchRun
    {
        return ThreadsSearchRun::factory()->for($user)->create([
            'queued_at' => $startedAt,
            'started_at' => $startedAt,
            'finished_at' => $startedAt,
            'queries_sent' => $queriesSent,
        ]);
    }
}
