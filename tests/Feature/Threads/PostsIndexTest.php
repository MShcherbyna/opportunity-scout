<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsSearchRunOutcome;
use App\Models\ThreadsConnection;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class PostsIndexTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    /**
     * SPEC-01 AC-61, AC-62, NFR-6: only the user's posts, newest post time first, 10 per page, in the Post shape.
     */
    public function test_lists_only_the_users_posts_newest_post_first_ten_per_page(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $posts = collect(range(0, 11))->map(fn (int $index) => $this->makePost($user, [
            'posted_at' => now()->subMinutes($index + 1),
            'text' => "Post number {$index}",
        ]));
        $this->makePost($other, ['posted_at' => now()->subSeconds(5)]);

        $response = $this->actingAs($user)->get(route('posts.index'));

        $props = $response->requiredProp('posts');
        $this->assertSame($posts->take(10)->pluck('id')->all(), array_column($props['data'], 'id'));
        $this->assertSame(1, $props['currentPage']);
        $this->assertSame(2, $props['lastPage']);
        $this->assertSame(10, $props['perPage']);
        $this->assertSame(12, $props['total']);
        $first = $props['data'][0];
        $this->assertEqualsCanonicalizing(
            ['id', 'threadsPostId', 'text', 'permalink', 'username', 'keyword', 'postedAt', 'foundAt'],
            array_keys($first),
        );
        $this->assertSame($posts[0]->threads_post_id, $first['threadsPostId']);
        $this->assertSame('Post number 0', $first['text']);
        $this->assertSame($posts[0]->username, $first['username']);
        $this->assertSame($posts[0]->permalink, $first['permalink']);
        $this->assertSame('laravel', $first['keyword']);
        $this->assertSame('2026-10-23 11:59:00', Carbon::parse($first['postedAt'])->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 12:00:00', Carbon::parse($first['foundAt'])->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-61: the second page holds the remaining posts.
     */
    public function test_second_page_holds_the_remaining_posts(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 11) as $index) {
            $this->makePost($user, ['posted_at' => now()->subMinutes($index + 1)]);
        }

        $props = $this->actingAs($user)->get(route('posts.index', ['page' => 2]))->requiredProp('posts');

        $this->assertCount(2, $props['data']);
        $this->assertSame(2, $props['currentPage']);
    }

    /**
     * SPEC-01 AC-63: the search term matches the text or the username.
     */
    public function test_search_matches_text_or_username(): void
    {
        $user = User::factory()->create();
        $byText = $this->makePost($user, ['text' => 'looking for a laravel developer', 'username' => 'bob']);
        $byUsername = $this->makePost($user, ['text' => 'nothing relevant', 'username' => 'laravelfan']);
        $this->makePost($user, ['text' => 'cooking tips', 'username' => 'alice']);
        $this->makePost(User::factory()->create(), ['text' => 'laravel for someone else', 'username' => 'carol']);

        $props = $this->actingAs($user)->get(route('posts.index', ['search' => 'laravel']))->requiredProp('posts');

        $this->assertEqualsCanonicalizing([$byText->id, $byUsername->id], array_column($props['data'], 'id'));
        $this->assertSame(2, $props['total']);
    }

    /**
     * SPEC-01 AC-63, UI-8: `%` and `_` in the search term match literally.
     */
    #[DataProvider('wildcardSearches')]
    public function test_search_treats_percent_and_underscore_literally(string $search, string $expectedText): void
    {
        $user = User::factory()->create();
        $this->makePost($user, ['text' => '100% sure']);
        $this->makePost($user, ['text' => '1000 sure']);
        $this->makePost($user, ['text' => 'a_b']);
        $this->makePost($user, ['text' => 'axb']);

        $props = $this->actingAs($user)->get(route('posts.index', ['search' => $search]))->requiredProp('posts');

        $this->assertSame([$expectedText], array_column($props['data'], 'text'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function wildcardSearches(): array
    {
        return [
            'percent sign' => ['100%', '100% sure'],
            'underscore' => ['a_b', 'a_b'],
        ];
    }

    /**
     * SPEC-01 AC-83: the keyword filter equals the stored keyword text, not a part of it.
     */
    public function test_keyword_filter_matches_the_stored_keyword_exactly(): void
    {
        $user = User::factory()->create();
        $exact = $this->makePost($user, ['keyword' => 'laravel']);
        $this->makePost($user, ['keyword' => 'laravel developer']);
        $this->makePost($user, ['keyword' => 'php']);
        $this->makePost(User::factory()->create(), ['keyword' => 'laravel']);

        $props = $this->actingAs($user)->get(route('posts.index', ['keyword' => 'laravel']))->requiredProp('posts');

        $this->assertSame([$exact->id], array_column($props['data'], 'id'));
    }

    /**
     * SPEC-01 AC-97: the applied filters are returned so the URL and the inputs stay in sync.
     */
    public function test_filters_are_returned_for_the_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('posts.index', ['search' => 'foo', 'keyword' => 'bar']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Posts')
                ->where('filters.search', 'foo')
                ->where('filters.keyword', 'bar')
            );

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', '')
                ->where('filters.keyword', null)
            );
    }

    /**
     * SPEC-01 AC-64: a page beyond the last shows the last page.
     */
    public function test_page_beyond_the_last_shows_the_last_page(): void
    {
        $user = User::factory()->create();
        foreach (range(0, 11) as $index) {
            $this->makePost($user, ['posted_at' => now()->subMinutes($index + 1)]);
        }

        $props = $this->actingAs($user)->get(route('posts.index', ['page' => 99]))->requiredProp('posts');

        $this->assertSame(2, $props['currentPage']);
        $this->assertCount(2, $props['data']);
    }

    /**
     * SPEC-01 UI-10: a `search` or `keyword` longer than 100 characters is rejected with HTTP 422.
     */
    #[DataProvider('overlongFilters')]
    public function test_overlong_search_or_keyword_is_rejected(string $parameter): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('posts.index', [$parameter => str_repeat('a', 101)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($parameter);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function overlongFilters(): array
    {
        return [
            'search' => ['search'],
            'keyword' => ['keyword'],
        ];
    }

    /**
     * SPEC-01 UI-8, UI-10: exactly 100 characters is still accepted.
     */
    public function test_search_and_keyword_of_exactly_100_characters_are_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('posts.index', ['search' => str_repeat('a', 100), 'keyword' => str_repeat('b', 100)]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', str_repeat('a', 100))
                ->where('filters.keyword', str_repeat('b', 100))
            );
    }

    /**
     * SPEC-01 AC-85, AC-96, RT-13: keyword options and count are the user's current keywords.
     */
    public function test_keyword_options_and_count_are_the_users_current_keywords(): void
    {
        $user = User::factory()->create();
        $this->makeKeyword($user, 'laravel');
        $this->makeKeyword($user, 'php');
        $this->makeKeyword(User::factory()->create(), 'not mine');

        $response = $this->actingAs($user)->get(route('posts.index'));

        $this->assertEqualsCanonicalizing(['laravel', 'php'], $response->requiredProp('keywordOptions'));
        $this->assertSame(2, $response->requiredProp('keywordCount'));
    }

    /**
     * SPEC-01 AC-90: the own-posts-only flag is passed to the Posts page.
     */
    public function test_own_posts_only_flag_is_passed_from_config(): void
    {
        config(['services.threads.own_posts_only' => true]);

        $this->actingAs(User::factory()->create())
            ->get(route('posts.index'))
            ->assertInertia(fn (Assert $page) => $page->where('ownPostsOnly', true));
    }

    /**
     * SPEC-01 AC-88, AC-111: the status reports an idle user as `idle` with no run data.
     */
    public function test_search_status_reports_an_idle_user(): void
    {
        $user = User::factory()->create();

        $status = $this->actingAs($user)->get(route('posts.index'))->requiredProp('searchStatus');

        $this->assertSame([
            'runState' => 'idle',
            'lastRunId' => null,
            'lastRunFinishedAt' => null,
            'lastRunPostsStored' => null,
            'lastRunOutcome' => null,
            'nextRunAt' => null,
            'pausedUntil' => null,
            'manualRunBlockedReason' => 'not_connected',
            'manualRunAvailableAt' => null,
        ], $status);
    }

    /**
     * SPEC-01 AC-88, AC-111: a queued run reports `queued`, a started run reports `running`.
     */
    #[DataProvider('activeRunStates')]
    public function test_search_status_reports_queued_running_and_idle_states(string $state, string $expected): void
    {
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');
        $factory = ThreadsSearchRun::factory()->for($user);
        match ($state) {
            'queued' => $factory->queued()->create(),
            'running' => $factory->running()->create(),
            'finished' => $factory->finished()->create(),
        };

        $status = $this->actingAs($user)->get(route('posts.index'))->requiredProp('searchStatus');

        $this->assertSame($expected, $status['runState']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function activeRunStates(): array
    {
        return [
            'queued run' => ['queued', 'queued'],
            'running run' => ['running', 'running'],
            'finished run' => ['finished', 'idle'],
        ];
    }

    /**
     * SPEC-01 AC-88, AC-113: the last finished run is reported with id, time, stored count and outcome.
     */
    public function test_search_status_reports_the_last_finished_run(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');
        ThreadsSearchRun::factory()->for($user)->create([
            'queued_at' => now()->subHours(3),
            'started_at' => now()->subHours(3),
            'finished_at' => now()->subHours(3)->addMinute(),
            'outcome' => ThreadsSearchRunOutcome::Completed,
            'posts_stored' => 9,
        ]);
        $last = ThreadsSearchRun::factory()->for($user)->create([
            'queued_at' => now()->subHour(),
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour()->addMinutes(2),
            'outcome' => ThreadsSearchRunOutcome::Partial,
            'posts_stored' => 4,
        ]);

        $status = $this->actingAs($user)->get(route('posts.index'))->requiredProp('searchStatus');

        $this->assertSame($last->id, $status['lastRunId']);
        $this->assertSame(4, $status['lastRunPostsStored']);
        $this->assertSame('partial', $status['lastRunOutcome']);
        $this->assertSame('2026-10-23 11:02:00', Carbon::parse($status['lastRunFinishedAt'])->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-45, AC-88: the next scheduled run is the last scheduled run plus the interval.
     */
    public function test_search_status_reports_the_next_run_time(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');
        ThreadsSearchRun::factory()->for($user)->scheduled()->create([
            'queued_at' => now()->subMinutes(5),
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
        ]);

        $status = $this->actingAs($user)->get(route('posts.index'))->requiredProp('searchStatus');

        $this->assertSame('2026-10-23 12:10:00', Carbon::parse($status['nextRunAt'])->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($status['manualRunBlockedReason']);
    }

    /**
     * SPEC-01 AC-58, AC-88: a paused search reports `pausedUntil` and no next run.
     */
    public function test_search_status_reports_the_pause(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->paused(now()->addHour())->create();
        $this->makeKeyword($user, 'laravel');

        $status = $this->actingAs($user)->get(route('posts.index'))->requiredProp('searchStatus');

        $this->assertSame('2026-10-23 13:00:00', Carbon::parse($status['pausedUntil'])->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($status['nextRunAt']);
        $this->assertSame('paused', $status['manualRunBlockedReason']);
    }

    /**
     * SPEC-01 AC-72: the status names the reason a manual run is refused.
     */
    #[DataProvider('manualRunBlockers')]
    public function test_search_status_reports_the_manual_run_blocked_reason(string $reason): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $this->arrangeManualRunBlocker($user, $reason);

        $status = $this->actingAs($user)->get(route('posts.index'))->requiredProp('searchStatus');

        $this->assertSame($reason, $status['manualRunBlockedReason']);
    }

    /**
     * SPEC-01 AC-72 (M-4): the cooldown reports when the next manual run is possible.
     */
    public function test_search_status_reports_when_the_cooldown_ends(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $this->arrangeManualRunBlocker($user, 'cooldown');

        $status = $this->actingAs($user)->get(route('posts.index'))->requiredProp('searchStatus');

        $this->assertSame('2026-10-23 12:10:00', Carbon::parse($status['manualRunAvailableAt'])->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function manualRunBlockers(): array
    {
        return [
            'M-1 not connected' => ['not_connected'],
            'M-2 no keywords' => ['no_keywords'],
            'M-3 run in progress' => ['run_in_progress'],
            'M-4 cooldown' => ['cooldown'],
            'M-5 paused' => ['paused'],
            'M-6 budget' => ['budget'],
        ];
    }

    /**
     * SPEC-01 AC-111, MC-3: a partial reload of `searchStatus` and `posts` returns only those props.
     */
    public function test_partial_reload_returns_only_search_status_and_posts(): void
    {
        $user = $this->createConnectedUser();
        $this->makePost($user);

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Posts')
                ->has('keywordOptions')
                ->reloadOnly(['searchStatus', 'posts'], fn (Assert $reload) => $reload
                    ->has('searchStatus')
                    ->has('posts.data', 1)
                    ->missing('filters')
                    ->missing('keywordOptions')
                    ->missing('keywordCount')
                    ->missing('ownPostsOnly')
                )
            );
    }

    /**
     * SPEC-01 NFR-6: guests are sent to the login page.
     */
    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('posts.index'))->assertRedirect(route('login'));
    }
}
