<?php

namespace Tests\Feature\Threads;

use App\Models\ThreadsConnection;
use App\Models\ThreadsKeyword;
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

        $this->assertSame(['postsFound' => 5, 'foundLast7Days' => 3, 'foundToday' => 1, 'relevantPosts' => 0], $stats);
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
        $this->assertSame(['date' => '2026-09-24', 'found' => 0, 'relevant' => 0], $activity[0]);
        $this->assertSame(['date' => '2026-10-21', 'found' => 1, 'relevant' => 0], $activity[27]);
        $this->assertSame(['date' => '2026-10-22', 'found' => 0, 'relevant' => 0], $activity[28]);
        $this->assertSame(['date' => '2026-10-23', 'found' => 2, 'relevant' => 0], $activity[29]);
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
        $this->assertSame(['date' => '2026-09-25', 'found' => 0, 'relevant' => 0], $activity[0]);
        $this->assertSame(['date' => '2026-10-23', 'found' => 1, 'relevant' => 0], $activity[28]);
        $this->assertSame(['date' => '2026-10-24', 'found' => 1, 'relevant' => 0], $activity[29]);
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
        $this->assertSame(['date' => '2026-10-23', 'found' => 1, 'relevant' => 0], $activity[28]);
        $this->assertSame(['date' => '2026-10-24', 'found' => 1, 'relevant' => 0], $activity[29]);
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
        $this->assertSame(['date' => '2026-09-24', 'found' => 0, 'relevant' => 0], $activity[0]);
        $this->assertSame(['date' => '2026-10-23', 'found' => 2, 'relevant' => 0], $activity[29]);
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
            ['id', 'threadsPostId', 'text', 'permalink', 'username', 'keyword', 'postedAt', 'foundAt', 'replyUrl', 'isRelevant'],
            array_keys($latest[0]),
        );
    }

    /**
     * SPEC-01 AC-118, AC-77: within the 5 most recently found posts, relevant-marked ones come first.
     */
    public function test_latest_posts_show_relevant_ones_first_within_the_five_most_recently_found(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $posts = collect(range(0, 6))->map(fn (int $index) => $this->makePost($user, [
            'created_at' => now()->subMinutes($index),
            'is_relevant' => false,
        ]));
        $posts[3]->update(['is_relevant' => true]);
        $posts[4]->update(['is_relevant' => true]);

        $latest = $this->actingAs($user)->get(route('dashboard'))->requiredProp('latestPosts');

        $this->assertSame(
            [$posts[3]->id, $posts[4]->id, $posts[0]->id, $posts[1]->id, $posts[2]->id],
            array_column($latest, 'id'),
        );
    }

    /**
     * SPEC-01 AC-118: a relevant post found outside the 5 most recently found window does not appear;
     * AC-118 reorders within the 5, it never widens or changes which 5 qualify.
     */
    public function test_relevant_post_outside_the_five_most_recently_found_is_not_shown(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $recent = collect(range(0, 4))->map(fn (int $index) => $this->makePost($user, [
            'created_at' => now()->subMinutes($index),
            'posted_at' => now()->subMinutes($index),
            'is_relevant' => false,
        ]));
        $this->makePost($user, [
            'created_at' => now()->subHour(),
            'posted_at' => now(),
            'is_relevant' => true,
        ]);

        $latest = $this->actingAs($user)->get(route('dashboard'))->requiredProp('latestPosts');

        $this->assertSame($recent->pluck('id')->all(), array_column($latest, 'id'));
    }

    /**
     * SPEC-01 AC-77, AC-116: a latest-posts row carries a replyUrl built from its permalink and the user's saved template.
     */
    public function test_latest_posts_carry_the_reply_url_with_the_users_template(): void
    {
        $user = User::factory()->create(['reply_template' => 'Thanks!']);
        $this->makePost($user, ['permalink' => 'https://www.threads.com/@alice/post/ABC123']);

        $latest = $this->actingAs($user)->get(route('dashboard'))->requiredProp('latestPosts');

        $this->assertSame(
            'https://www.threads.com/intent/post?reply_post_shortcode=ABC123&text=Thanks%21',
            $latest[0]['replyUrl'],
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
        $this->assertSame(
            ['postsFound' => 0, 'foundLast7Days' => 0, 'foundToday' => 0, 'relevantPosts' => 0],
            $response->requiredProp('stats'),
        );
        $this->assertSame(0, $response->requiredProp('threadsQuota.usedLast24Hours'));
        $this->assertSame(0, array_sum(array_column($response->requiredProp('activity'), 'found')));
    }

    /**
     * SPEC-01 AC-105, NFR-6: "Relevant posts" counts only the user's marked posts, with no time window.
     */
    public function test_relevant_posts_stat_counts_the_users_marked_posts(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $this->makePost($user, ['is_relevant' => true]);
        $this->makePost($user, ['is_relevant' => true, 'created_at' => now()->subDays(60)]);
        $this->makePost($user, ['is_relevant' => false]);
        $this->makePost(User::factory()->create(), ['is_relevant' => true]);

        $stats = $this->actingAs($user)->get(route('dashboard'))->requiredProp('stats');

        $this->assertSame(2, $stats['relevantPosts']);
    }

    /**
     * SPEC-01 AC-105, RT-16: each day's `relevant` count is that day's found posts currently marked relevant.
     */
    public function test_activity_series_counts_relevant_posts_per_found_day(): void
    {
        $this->travelTo('2026-10-23 15:00:00');
        $user = User::factory()->create();
        $this->makePost($user, ['created_at' => '2026-10-23 01:00:00', 'is_relevant' => true]);
        $this->makePost($user, ['created_at' => '2026-10-23 14:00:00', 'is_relevant' => false]);
        $this->makePost($user, ['created_at' => '2026-10-21 09:00:00', 'is_relevant' => true]);

        $activity = $this->actingAs($user)->get(route('dashboard'))->requiredProp('activity');

        $this->assertSame(['date' => '2026-10-21', 'found' => 1, 'relevant' => 1], $activity[27]);
        $this->assertSame(['date' => '2026-10-23', 'found' => 2, 'relevant' => 1], $activity[29]);
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

    /**
     * SPEC-01 AC-114, MC-8: the Dashboard banner source.
     */
    #[DataProvider('readinessStates')]
    public function test_dashboard_search_status_names_the_readiness_banner_state(callable $arrange, ?string $expected): void
    {
        $user = User::factory()->create();
        $arrange($user);

        $status = $this->actingAs($user)->get(route('dashboard'))->requiredProp('searchStatus');

        $this->assertSame($expected, $status['manualRunBlockedReason']);
    }

    /**
     * @return array<string, array{callable(User): void, string|null}>
     */
    public static function readinessStates(): array
    {
        return [
            'no connection' => [fn (User $user) => null, 'not_connected'],
            'disconnected connection' => [function (User $user): void {
                ThreadsConnection::factory()->for($user)->disconnected()->create();
                ThreadsKeyword::factory()->for($user)->create(['keyword' => 'laravel']);
            }, 'not_connected'],
            'connected, no keywords' => [function (User $user): void {
                ThreadsConnection::factory()->for($user)->create();
            }, 'no_keywords'],
            'connected with keywords' => [function (User $user): void {
                ThreadsConnection::factory()->for($user)->create();
                ThreadsKeyword::factory()->for($user)->create(['keyword' => 'laravel']);
            }, null],
        ];
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
