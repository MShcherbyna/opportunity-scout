<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Enums\ThreadsSearchRunOutcome;
use App\Enums\ThreadsSearchRunTrigger;
use App\Events\ThreadsPostsStored;
use App\Jobs\RunThreadsSearch;
use App\Models\ThreadsConnection;
use App\Models\ThreadsKeyword;
use App\Models\ThreadsPost;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class RunThreadsSearchJobTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    private const TOKEN = 'long-lived-token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureThreads();
        $this->travelTo('2026-10-23 12:00:00');
    }

    /**
     * SPEC-01 AC-48: one request per keyword with its own window start, the run start as `until`, and the decrypted token.
     */
    public function test_sends_one_keyword_search_per_keyword_with_window_and_run_start(): void
    {
        $this->fakeKeywordSearch();
        [$user] = $this->searchableUser([
            'laravel' => '2026-10-23 11:00:00',
            'php' => '2026-10-22 12:00:00',
        ]);

        $this->execute($this->queuedRun($user));

        $this->assertCount(2, Http::recorded());
        $byKeyword = collect(Http::recorded())->mapWithKeys(function (array $pair) {
            $parameters = $this->requestParameters($pair[0]);

            return [$parameters['q'] => $parameters];
        });
        $until = Carbon::parse('2026-10-23 12:00:00', 'UTC')->timestamp;
        $this->assertSame((string) Carbon::parse('2026-10-23 11:00:00', 'UTC')->timestamp, (string) $byKeyword['laravel']['since']);
        $this->assertSame((string) Carbon::parse('2026-10-22 12:00:00', 'UTC')->timestamp, (string) $byKeyword['php']['since']);
        foreach ($byKeyword as $parameters) {
            $this->assertSame((string) $until, (string) $parameters['until']);
            $this->assertSame('RECENT', $parameters['search_type']);
            $this->assertSame('100', (string) $parameters['limit']);
            $this->assertSame('id,text,permalink,timestamp,username', $parameters['fields']);
            $this->assertSame(self::TOKEN, $parameters['access_token']);
        }
    }

    /**
     * SPEC-01 EC-34, S-6: `since` is never below 1688540400.
     */
    public function test_since_never_drops_below_the_threads_minimum_timestamp(): void
    {
        $this->fakeKeywordSearch();
        [$user] = $this->searchableUser(['laravel' => '2020-01-01 00:00:00']);

        $this->execute($this->queuedRun($user));

        $this->assertCount(1, Http::recorded());
        [$request] = Http::recorded()[0];
        $this->assertSame('1688540400', (string) $this->requestParameters($request)['since']);
    }

    /**
     * SPEC-01 AC-50, D-7: a successful query (even an empty one) moves the window to `until` minus 10 minutes.
     */
    public function test_successful_query_moves_the_window_to_until_minus_ten_minutes(): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([$this->threadsItem('1')])]);
        [$user, $keywords] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);

        $this->execute($this->queuedRun($user));

        $this->assertSame('2026-10-23 11:50:00', $keywords['laravel']->fresh()->search_window_start->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 11:50:00', $keywords['php']->fresh()->search_window_start->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-51: a failed query leaves its window (and search time) unchanged so the next run covers the gap.
     */
    public function test_failed_query_keeps_the_window(): void
    {
        $this->fakeKeywordSearch(['laravel' => Http::response([], 500)]);
        [$user, $keywords] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);

        $this->execute($this->queuedRun($user));

        $failed = $keywords['laravel']->fresh();
        $this->assertSame('2026-10-23 11:00:00', $failed->search_window_start->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($failed->last_searched_at);
        $this->assertSame('2026-10-23 11:50:00', $keywords['php']->fresh()->search_window_start->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-52: a new post is stored unfiltered with all returned fields, its keyword and its owner.
     */
    public function test_stores_new_posts_with_all_fields_and_owner(): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([
            $this->threadsItem('1789', [
                'text' => 'Need a laravel dev',
                'permalink' => 'https://www.threads.com/@alice/post/ABC',
                'timestamp' => '2026-10-23T11:30:00+0000',
                'username' => 'alice',
            ]),
        ])]);
        [$user, $keywords] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $this->assertSame(1, $user->threadsPosts()->count());
        $post = $user->threadsPosts()->firstOrFail();
        $this->assertSame('1789', $post->threads_post_id);
        $this->assertSame('Need a laravel dev', $post->text);
        $this->assertSame('https://www.threads.com/@alice/post/ABC', $post->permalink);
        $this->assertSame('alice', $post->username);
        $this->assertSame('laravel', $post->keyword);
        $this->assertSame($keywords['laravel']->id, $post->threads_keyword_id);
        $this->assertSame('2026-10-23 11:30:00', $post->posted_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 12:00:00', $post->created_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(1, $run->fresh()->posts_stored);
    }

    /**
     * SPEC-01 AC-52, S-1: the Threads timestamp is converted to UTC; a post without text is stored with null text.
     */
    public function test_post_timestamp_is_converted_to_utc_and_media_only_posts_are_stored(): void
    {
        $item = $this->threadsItem('55', ['timestamp' => '2026-10-23T13:30:00+0200']);
        unset($item['text']);
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([$item])]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);

        $this->execute($this->queuedRun($user));

        $this->assertSame(1, $user->threadsPosts()->count());
        $post = $user->threadsPosts()->firstOrFail();
        $this->assertSame('2026-10-23 11:30:00', $post->posted_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($post->text);
    }

    /**
     * SPEC-01 AC-53: a post id already stored for the user is skipped and the stored post, including its keyword, stays.
     */
    public function test_skips_posts_already_stored_for_the_user_and_keeps_their_keyword(): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([
            $this->threadsItem('1789', ['text' => 'new text']),
            $this->threadsItem('1790'),
        ])]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $existing = $this->makePost($user, ['threads_post_id' => '1789', 'text' => 'old text', 'keyword' => 'old keyword']);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $this->assertEqualsCanonicalizing(['1789', '1790'], $user->threadsPosts()->pluck('threads_post_id')->all());
        $this->assertSame('old text', $existing->fresh()->text);
        $this->assertSame('old keyword', $existing->fresh()->keyword);
        $this->assertSame(1, $run->fresh()->posts_stored);
    }

    /**
     * SPEC-01 AC-53: the same Threads post id of another user does not block storing it for this user.
     */
    public function test_same_post_id_for_another_user_is_stored(): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([$this->threadsItem('1789')])]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $otherUsersPost = $this->makePost(User::factory()->create(), ['threads_post_id' => '1789']);

        $this->execute($this->queuedRun($user));

        $this->assertSame(1, $user->threadsPosts()->count());
        $this->assertSame(2, ThreadsPost::query()->where('threads_post_id', '1789')->count());
        $this->assertModelExists($otherUsersPost);
    }

    /**
     * SPEC-01 EC-19: a post matching two keywords in one run is stored once.
     */
    public function test_a_post_matching_two_keywords_is_stored_once(): void
    {
        $page = $this->threadsSearchPage([$this->threadsItem('1789')]);
        $this->fakeKeywordSearch(['laravel' => $page, 'php' => $page]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $this->assertSame(1, $user->threadsPosts()->count());
        $this->assertSame(1, $run->fresh()->posts_stored);
    }

    /**
     * SPEC-01 AC-54: an empty result records zero results and the run continues with the next keyword.
     */
    public function test_empty_result_records_zero_results_and_continues(): void
    {
        $this->fakeKeywordSearch([
            'laravel' => $this->threadsSearchPage([]),
            'php' => $this->threadsSearchPage([$this->threadsItem('1')]),
        ]);
        [$user, $keywords] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);

        $this->execute($this->queuedRun($user));

        $this->assertCount(2, Http::recorded());
        $empty = $keywords['laravel']->fresh();
        $this->assertSame(0, $empty->last_result_count);
        $this->assertSame('2026-10-23 12:00:00', $empty->last_searched_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(1, $user->threadsPosts()->count());
    }

    /**
     * @return array<string, array{?string, bool, ?string}>
     */
    public static function zeroResultTransitions(): array
    {
        return [
            'first empty result sets it' => [null, false, '2026-10-23 12:00:00'],
            'repeated empty result keeps the first time' => ['2026-10-16 00:00:00', false, '2026-10-16 00:00:00'],
            'a non-empty result clears it' => ['2026-10-16 00:00:00', true, null],
        ];
    }

    /**
     * SPEC-01 AC-92 (data model): `zero_results_since` starts at the first empty result and is cleared by posts.
     */
    #[DataProvider('zeroResultTransitions')]
    public function test_zero_results_since_is_set_on_first_empty_result_and_cleared_by_a_non_empty_one(?string $before, bool $returnsPosts, ?string $expected): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage($returnsPosts ? [$this->threadsItem('1')] : [])]);
        [$user, $keywords] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $keywords['laravel']->update(['zero_results_since' => $before]);

        $this->execute($this->queuedRun($user));

        $stored = $keywords['laravel']->fresh();
        $this->assertNotNull($stored->last_searched_at);
        $this->assertSame('2026-10-23 12:00:00', $stored->last_searched_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($expected, $stored->zero_results_since?->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-95: the last successful search time and the number of posts it returned are recorded (duplicates included).
     */
    public function test_records_last_searched_at_and_result_count(): void
    {
        $this->fakeKeywordSearch([
            'laravel' => $this->threadsSearchPage([$this->threadsItem('1'), $this->threadsItem('2')]),
            'php' => Http::response([], 500),
        ]);
        [$user, $keywords] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);
        $this->makePost($user, ['threads_post_id' => '1']);

        $this->execute($this->queuedRun($user));

        $searched = $keywords['laravel']->fresh();
        $this->assertSame(2, $searched->last_result_count);
        $this->assertSame('2026-10-23 12:00:00', $searched->last_searched_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($keywords['php']->fresh()->last_searched_at);
    }

    /**
     * SPEC-01 AC-16, AC-55: error 190 disconnects with reason token_invalid and ends the run after the first query.
     */
    public function test_error_190_disconnects_and_ends_the_run(): void
    {
        $failure = $this->threadsError(190, 'Error validating access token');
        $this->fakeKeywordSearch(['laravel' => $failure, 'php' => $failure]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $this->assertCount(1, Http::recorded());
        $connection = $user->threadsConnection()->firstOrFail();
        $this->assertSame(ThreadsConnectionStatus::Disconnected, $connection->status);
        $this->assertSame(ThreadsDisconnectReason::TokenInvalid, $connection->disconnect_reason);
        $this->assertNull(DB::table('threads_connections')->where('id', $connection->id)->value('access_token'));
        $this->assertNotNull($run->fresh()->finished_at);
    }

    /**
     * SPEC-01 AC-56: network errors, timeouts and 5xx log a warning with the keyword id and the run continues.
     */
    #[DataProvider('transientFailures')]
    public function test_transient_error_logs_a_warning_and_continues(mixed $failure): void
    {
        $this->fakeKeywordSearch([
            'laravel' => is_int($failure) ? Http::response([], $failure) : $failure,
            'php' => $this->threadsSearchPage([$this->threadsItem('1')]),
        ]);
        $logs = $this->captureLogs();
        [$user, $keywords] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);

        $this->execute($this->queuedRun($user));

        $this->assertCount(2, Http::recorded());
        $warnings = array_filter($this->logsAt($logs, 'warning'), fn ($log) => $this->logMentionsNumber($log, $keywords['laravel']->id));
        $this->assertNotEmpty($warnings);
        $this->assertNoLogContains($logs, self::TOKEN);
        $this->assertSame(1, $user->threadsPosts()->count());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function transientFailures(): array
    {
        return [
            'http 500' => [500],
            'http 503' => [503],
            'connection failure' => [HttpFactory::failedConnection('cURL error 28: Operation timed out')],
        ];
    }

    /**
     * SPEC-01 AC-57: any other error logs an error with the keyword id and the Threads code and the run continues.
     */
    public function test_other_error_logs_an_error_and_continues(): void
    {
        $this->fakeKeywordSearch([
            'laravel' => $this->threadsError(100, 'Invalid parameter'),
            'php' => $this->threadsSearchPage([$this->threadsItem('1')]),
        ]);
        $logs = $this->captureLogs();
        [$user, $keywords] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);

        $this->execute($this->queuedRun($user));

        $this->assertCount(2, Http::recorded());
        $errors = array_filter(
            $this->logsAt($logs, 'error'),
            fn ($log) => $this->logMentionsNumber($log, $keywords['laravel']->id) && $this->logMentionsNumber($log, 100),
        );
        $this->assertNotEmpty($errors);
        $this->assertSame(1, $user->threadsPosts()->count());
    }

    /**
     * SPEC-01 AC-58, AC-74, UI-11: a rate limit ends the run, pauses for one hour and logs the body once, without the token.
     */
    public function test_rate_limit_ends_the_run_pauses_for_one_hour_and_logs_the_body_once_without_token(): void
    {
        $failure = $this->threadsError(17, 'User request limit reached');
        $this->fakeKeywordSearch(['laravel' => $failure, 'php' => $failure]);
        $logs = $this->captureLogs();
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $this->assertCount(1, Http::recorded());
        $this->assertSame(ThreadsSearchRunOutcome::PausedQuota, $run->fresh()->outcome);
        $this->assertSame('2026-10-23 13:00:00', $user->threadsConnection()->firstOrFail()->search_paused_until->utc()->format('Y-m-d H:i:s'));
        $withBody = array_filter($logs->getArrayCopy(), fn ($log) => str_contains($this->logText($log), 'trace123'));
        $this->assertCount(1, $withBody);
        $this->assertNoLogContains($logs, self::TOKEN);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function budgetUsage(): array
    {
        return [
            '1999 sent allows exactly one more query' => [1999, 1],
            '2000 sent allows none' => [2000, 0],
        ];
    }

    /**
     * SPEC-01 AC-59, AC-60: at 2,000 queries in the last 24 hours no request goes out and the outcome is budget_reached.
     */
    #[DataProvider('budgetUsage')]
    public function test_stops_with_budget_reached_at_2000_queries_in_the_last_24_hours(int $alreadySent, int $expectedRequests): void
    {
        $this->fakeKeywordSearch();
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00', 'remote' => '2026-10-23 11:00:00']);
        $this->finishedRun($user, now()->subHours(2), $alreadySent);
        $this->finishedRun($user, now()->subHours(25), 500);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $this->assertCount($expectedRequests, Http::recorded());
        $stored = $run->fresh();
        $this->assertSame(ThreadsSearchRunOutcome::BudgetReached, $stored->outcome);
        $this->assertSame($expectedRequests, $stored->queries_sent);
    }

    /**
     * @return array<string, array{string, ThreadsSearchRunOutcome, int, int}>
     */
    public static function outcomes(): array
    {
        return [
            'completed' => ['completed', ThreadsSearchRunOutcome::Completed, 1, 2],
            'partial' => ['partial', ThreadsSearchRunOutcome::Partial, 2, 1],
            'paused_quota' => ['paused_quota', ThreadsSearchRunOutcome::PausedQuota, 1, 0],
            'budget_reached' => ['budget_reached', ThreadsSearchRunOutcome::BudgetReached, 0, 0],
            'stopped_disconnected' => ['stopped_disconnected', ThreadsSearchRunOutcome::StoppedDisconnected, 0, 0],
        ];
    }

    /**
     * SPEC-01 AC-60: the run records its trigger, start and end time, queries sent, new posts and outcome.
     */
    #[DataProvider('outcomes')]
    public function test_records_trigger_times_counts_and_outcome(string $scenario, ThreadsSearchRunOutcome $outcome, int $queries, int $posts): void
    {
        $keywords = $scenario === 'partial' ? ['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00'] : ['laravel' => '2026-10-23 11:00:00'];
        [$user] = $this->searchableUser($keywords);
        $this->arrangeScenario($user, $scenario);
        $run = $this->queuedRun($user, ['trigger' => ThreadsSearchRunTrigger::Manual]);

        $this->execute($run);

        $stored = $run->fresh();
        $this->assertSame($outcome, $stored->outcome);
        $this->assertSame(ThreadsSearchRunTrigger::Manual, $stored->trigger);
        $this->assertSame($queries, $stored->queries_sent);
        $this->assertSame($posts, $stored->posts_stored);
        $this->assertSame('2026-10-23 12:00:00', $stored->started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNotNull($stored->finished_at);
        $this->assertTrue($stored->finished_at->greaterThanOrEqualTo($stored->started_at));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function missingConnections(): array
    {
        return [
            'no connection row' => [false],
            'disconnected connection' => [true],
        ];
    }

    /**
     * SPEC-01 AC-12, AC-60: without a `connected` connection no Threads request is sent and the run ends as stopped_disconnected.
     */
    #[DataProvider('missingConnections')]
    public function test_disconnected_or_missing_connection_sends_no_request(bool $hasDisconnectedRow): void
    {
        $this->fakeKeywordSearch();
        $user = User::factory()->create();
        if ($hasDisconnectedRow) {
            ThreadsConnection::factory()->for($user)->disconnected(ThreadsDisconnectReason::Uninstalled)->create();
        }
        $this->makeKeyword($user, 'laravel');
        $run = $this->queuedRun($user);

        $this->execute($run);

        Http::assertNothingSent();
        $stored = $run->fresh();
        $this->assertSame(ThreadsSearchRunOutcome::StoppedDisconnected, $stored->outcome);
        $this->assertSame(0, $stored->queries_sent);
        $this->assertNotNull($stored->finished_at);
    }

    /**
     * SPEC-01 AC-73: a run that starts while the user's search is paused sends nothing.
     */
    public function test_paused_user_sends_no_request(): void
    {
        $this->fakeKeywordSearch();
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $user->threadsConnection()->update(['search_paused_until' => now()->addMinutes(30)]);
        $run = $this->queuedRun($user);

        $this->execute($run);

        Http::assertNothingSent();
        $stored = $run->fresh();
        $this->assertSame(ThreadsSearchRunOutcome::PausedQuota, $stored->outcome);
        $this->assertSame(0, $stored->queries_sent);
        $this->assertSame('2026-10-23 12:30:00', $user->threadsConnection()->firstOrFail()->search_paused_until->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-40: a keyword deleted before the run starts is not searched.
     */
    public function test_keyword_deleted_before_the_run_starts_is_not_searched(): void
    {
        $this->fakeKeywordSearch();
        [$user, $keywords] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);
        $run = $this->queuedRun($user);
        $keywords['php']->delete();

        $this->execute($run);

        $this->assertCount(1, Http::recorded());
        [$request] = Http::recorded()[0];
        $this->assertSame('laravel', $this->requestParameters($request)['q']);
    }

    /**
     * SPEC-01 AC-78, MC-9: the event carries the user id and the ids of the new posts only.
     */
    public function test_dispatches_posts_stored_event_with_the_new_post_ids(): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([
            $this->threadsItem('1'),
            $this->threadsItem('2'),
            $this->threadsItem('3'),
        ])]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $this->makePost($user, ['threads_post_id' => '3']);
        $run = $this->queuedRun($user);
        Event::fake([ThreadsPostsStored::class]);

        $this->execute($run);

        $newIds = $user->threadsPosts()->whereIn('threads_post_id', ['1', '2'])->pluck('id')->sort()->values()->all();
        $this->assertCount(2, $newIds);
        Event::assertDispatchedTimes(ThreadsPostsStored::class, 1);
        Event::assertDispatched(ThreadsPostsStored::class, function (ThreadsPostsStored $event) use ($user, $newIds) {
            $postIds = $event->postIds;
            sort($postIds);

            return $event->userId === $user->id && $postIds === $newIds;
        });
    }

    /**
     * @return array<string, array{array<int, array<string, mixed>>}>
     */
    public static function runsWithoutNewPosts(): array
    {
        return [
            'empty result' => [[]],
            'only already stored posts' => [[['id' => '3']]],
        ];
    }

    /**
     * SPEC-01 AC-78: no event is dispatched when the run stored no new post.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    #[DataProvider('runsWithoutNewPosts')]
    public function test_does_not_dispatch_posts_stored_event_without_new_posts(array $items): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage(array_map(fn (array $item) => $this->threadsItem($item['id']), $items))]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $this->makePost($user, ['threads_post_id' => '3']);
        $run = $this->queuedRun($user);
        Event::fake([ThreadsPostsStored::class]);

        $this->execute($run);

        $this->assertSame(ThreadsSearchRunOutcome::Completed, $run->fresh()->outcome);
        Event::assertNotDispatched(ThreadsPostsStored::class);
    }

    /**
     * SPEC-01 AC-104: finding posts sends no notification or mail.
     */
    public function test_sends_no_notifications(): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([$this->threadsItem('1')])]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $run = $this->queuedRun($user);
        Notification::fake();
        Mail::fake();

        $this->execute($run);

        $this->assertSame(1, $user->threadsPosts()->count());
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPermalinks(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(1)'],
            'http instead of https' => ['http://threads.net/@alice/post/1'],
            'another site' => ['https://evil.com/@alice/post/1'],
            'allowed host as a prefix of another host' => ['https://threads.com.evil.com/@alice/post/1'],
            'allowed host as a subdomain prefix' => ['https://threads.net.evil.com/@alice/post/1'],
        ];
    }

    /**
     * SPEC-01 UI-5: a permalink outside the allowed https hosts is dropped; the post itself is still stored.
     */
    #[DataProvider('invalidPermalinks')]
    public function test_invalid_permalinks_are_stored_as_null(string $permalink): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([$this->threadsItem('1', ['permalink' => $permalink])])]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);

        $this->execute($this->queuedRun($user));

        $this->assertSame(1, $user->threadsPosts()->count());
        $this->assertNull($user->threadsPosts()->firstOrFail()->permalink);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedPermalinks(): array
    {
        return [
            'threads.com' => ['https://threads.com/@alice/post/1'],
            'www.threads.com' => ['https://www.threads.com/@alice/post/1'],
            'threads.net' => ['https://threads.net/@alice/post/1'],
            'www.threads.net' => ['https://www.threads.net/@alice/post/1'],
        ];
    }

    /**
     * SPEC-01 UI-5: https permalinks on the four Threads hosts are kept.
     */
    #[DataProvider('allowedPermalinks')]
    public function test_permalinks_on_the_allowed_threads_hosts_are_kept(string $permalink): void
    {
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([$this->threadsItem('1', ['permalink' => $permalink])])]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);

        $this->execute($this->queuedRun($user));

        $this->assertSame(1, $user->threadsPosts()->count());
        $this->assertSame($permalink, $user->threadsPosts()->firstOrFail()->permalink);
    }

    /**
     * SPEC-01 UI-6: items without an id or without a parseable timestamp are skipped with a warning naming the keyword.
     */
    public function test_items_without_id_or_timestamp_are_skipped_with_a_warning(): void
    {
        $withoutId = $this->threadsItem('x');
        unset($withoutId['id']);
        $withoutTimestamp = $this->threadsItem('2');
        unset($withoutTimestamp['timestamp']);
        $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([
            $withoutId,
            $withoutTimestamp,
            $this->threadsItem('3', ['timestamp' => 'not a timestamp']),
            $this->threadsItem('4'),
        ])]);
        $logs = $this->captureLogs();
        [$user, $keywords] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $this->assertSame(['4'], $user->threadsPosts()->pluck('threads_post_id')->all());
        $this->assertSame(1, $run->fresh()->posts_stored);
        $warnings = array_filter($this->logsAt($logs, 'warning'), fn ($log) => $this->logMentionsNumber($log, $keywords['laravel']->id));
        $this->assertNotEmpty($warnings);
    }

    /**
     * SPEC-01 AC-8, NFR-2: the queued job carries only the run id, never a token or a keyword.
     */
    public function test_job_payload_contains_only_the_run_id(): void
    {
        Queue::fake([RunThreadsSearch::class]);
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);

        Artisan::call('threads:dispatch-search-runs');

        $this->assertSame(1, $user->threadsSearchRuns()->count());
        $run = $user->threadsSearchRuns()->firstOrFail();
        Queue::assertPushed(RunThreadsSearch::class, function (RunThreadsSearch $job) use ($run) {
            $serialized = serialize($job);

            $this->assertSame($run->id, $job->searchRunId);
            $this->assertStringNotContainsString(self::TOKEN, $serialized);
            $this->assertStringNotContainsString('laravel', $serialized);

            return true;
        });
    }

    /**
     * SPEC-01 EC-46, plan section 3: a job that fails closes its unfinished run as `partial`.
     */
    public function test_failed_job_closes_the_run_as_partial(): void
    {
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $run = $this->queuedRun($user, ['started_at' => now()]);

        (new RunThreadsSearch($run->id))->failed(new \RuntimeException('worker crashed'));

        $stored = $run->fresh();
        $this->assertSame(ThreadsSearchRunOutcome::Partial, $stored->outcome);
        $this->assertSame('2026-10-23 12:00:00', $stored->finished_at->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-60: a failure after the run has ended does not rewrite its recorded outcome.
     */
    public function test_failed_job_leaves_an_already_finished_run_unchanged(): void
    {
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $run = ThreadsSearchRun::factory()->for($user)->create([
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
            'outcome' => ThreadsSearchRunOutcome::Completed,
        ]);
        $before = $run->fresh()->getAttributes();

        (new RunThreadsSearch($run->id))->failed(new \RuntimeException('late failure'));

        $this->assertSame($before, $run->fresh()->getAttributes());
    }

    /**
     * SPEC-01 AC-47 (plan section 3): a run that is already finished is not executed again.
     */
    public function test_a_finished_run_is_not_executed_again(): void
    {
        $this->fakeKeywordSearch();
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00']);
        $finished = ThreadsSearchRun::factory()->for($user)->create([
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
            'outcome' => ThreadsSearchRunOutcome::Completed,
        ]);
        [$otherUser] = $this->searchableUser(['php' => '2026-10-23 11:00:00']);
        $queued = $this->queuedRun($otherUser);

        $this->execute($finished);
        $this->execute($queued);

        $this->assertCount(1, Http::recorded());
        [$request] = Http::recorded()[0];
        $this->assertSame('php', $this->requestParameters($request)['q']);
    }

    /**
     * SPEC-01 NFR-9: one summary entry per run with user id, trigger, queries sent, new posts and outcome.
     */
    public function test_writes_one_summary_log_entry_per_run(): void
    {
        $this->fakeKeywordSearch([
            'laravel' => $this->threadsSearchPage([$this->threadsItem('1'), $this->threadsItem('2')]),
            'php' => $this->threadsSearchPage([$this->threadsItem('3')]),
        ]);
        $logs = $this->captureLogs();
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);

        $this->execute($this->queuedRun($user));

        $summaries = $this->logsAt($logs, 'info');
        $this->assertCount(1, $summaries);
        $values = $this->logValues($summaries[0]);
        $this->assertContains((string) $user->id, $values);
        $this->assertContains('scheduled', $values);
        $this->assertContains('completed', $values);
        $this->assertContains('2', $values);
        $this->assertContains('3', $values);
    }

    /**
     * @return array<string, array{Closure}>
     */
    public static function midRunConnectionLosses(): array
    {
        return [
            'disconnected' => [fn (ThreadsConnection $connection) => $connection->markDisconnected(ThreadsDisconnectReason::User)],
            'deleted' => [fn (ThreadsConnection $connection) => $connection->delete()],
        ];
    }

    /**
     * SPEC-01 AC-12, AC-14 (fix AR-1): a connection that is disconnected or deleted during the first query stores no
     * posts, sends no further request and ends the run as stopped_disconnected.
     */
    #[DataProvider('midRunConnectionLosses')]
    public function test_connection_lost_mid_run_stores_nothing_and_stops(Closure $lose): void
    {
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);
        $connection = $user->threadsConnection()->firstOrFail();
        $this->fakeKeywordSearch(['laravel' => function () use ($lose, $connection) {
            $lose(ThreadsConnection::query()->findOrFail($connection->id));

            return $this->threadsSearchPage([$this->threadsItem('1'), $this->threadsItem('2')]);
        }]);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $this->assertCount(1, Http::recorded());
        $this->assertSame(0, $user->threadsPosts()->count());
        $stored = $run->fresh();
        $this->assertSame(ThreadsSearchRunOutcome::StoppedDisconnected, $stored->outcome);
        $this->assertSame(0, $stored->posts_stored);
        $this->assertNotNull($stored->finished_at);
    }

    /**
     * SPEC-01 AC-16, AC-55 (fix AR-2): error 190 in a run that holds a stale model does not wipe a token
     * the user reconnected meanwhile.
     */
    public function test_token_invalid_from_a_stale_run_does_not_wipe_a_reconnected_token(): void
    {
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);
        $connection = $user->threadsConnection()->firstOrFail();
        $this->fakeKeywordSearch(['laravel' => function () use ($connection) {
            ThreadsConnection::query()->findOrFail($connection->id)->update([
                'access_token' => 'reconnected-token',
                'token_issued_at' => now()->addMinute(),
                'connected_at' => now()->addMinute(),
            ]);

            return $this->threadsError(190, 'Error validating access token');
        }]);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Connected, $stored->status);
        $this->assertNull($stored->disconnect_reason);
        $this->assertSame('reconnected-token', $stored->access_token);
        $this->assertCount(1, Http::recorded());
        $this->assertSame(ThreadsSearchRunOutcome::StoppedDisconnected, $run->fresh()->outcome);
    }

    /**
     * SPEC-01 AC-12, AC-13 (fix AR-12): a connection reconnected (new `token_issued_at`) during the first query sends no
     * further request with the old token, stores nothing after that point and ends the run as stopped_disconnected.
     */
    public function test_reconnect_during_the_first_query_stops_the_run_without_storing_anything(): void
    {
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);
        $connection = $user->threadsConnection()->firstOrFail();
        $this->fakeKeywordSearch(['laravel' => function () use ($connection) {
            ThreadsConnection::query()->findOrFail($connection->id)->update([
                'access_token' => 'reconnected-token',
                'token_issued_at' => now()->addMinute(),
                'connected_at' => now()->addMinute(),
            ]);

            return $this->threadsSearchPage([$this->threadsItem('1'), $this->threadsItem('2')]);
        }]);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $this->assertCount(1, Http::recorded());
        [$request] = Http::recorded()[0];
        $this->assertSame(self::TOKEN, $this->requestParameters($request)['access_token']);
        $this->assertSame(0, $user->threadsPosts()->count());
        $stored = $run->fresh();
        $this->assertSame(ThreadsSearchRunOutcome::StoppedDisconnected, $stored->outcome);
        $this->assertSame(0, $stored->posts_stored);
        $this->assertNotNull($stored->finished_at);
        $storedConnection = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Connected, $storedConnection->status);
        $this->assertSame('reconnected-token', $storedConnection->access_token);
    }

    /**
     * SPEC-01 AC-12, AC-13 (fix AR-14): a token refresh during the first query (new token, same `connected_at`) is not a
     * reconnect, so the run continues to every keyword and completes.
     */
    public function test_token_refresh_during_the_first_query_does_not_stop_the_run(): void
    {
        [$user] = $this->searchableUser(['laravel' => '2026-10-23 11:00:00', 'php' => '2026-10-23 11:00:00']);
        $connection = $user->threadsConnection()->firstOrFail();
        $this->fakeKeywordSearch([
            'laravel' => function () use ($connection) {
                ThreadsConnection::query()->findOrFail($connection->id)->update([
                    'access_token' => 'refreshed-token',
                    'token_issued_at' => now()->addMinute(),
                ]);

                return $this->threadsSearchPage([$this->threadsItem('1')]);
            },
            'php' => $this->threadsSearchPage([$this->threadsItem('2')]),
        ]);
        $run = $this->queuedRun($user);

        $this->execute($run);

        $this->assertCount(2, Http::recorded());
        $this->assertSame(2, $user->threadsPosts()->count());
        $stored = $run->fresh();
        $this->assertSame(ThreadsSearchRunOutcome::Completed, $stored->outcome);
        $this->assertSame(2, $stored->queries_sent);
        $this->assertSame(ThreadsConnectionStatus::Connected, $connection->fresh()->status);
    }

    /**
     * @param  array<string, string>  $keywordsWithWindowStart
     * @return array{User, array<string, ThreadsKeyword>}
     */
    private function searchableUser(array $keywordsWithWindowStart): array
    {
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->create(['access_token' => self::TOKEN]);
        $keywords = [];

        foreach ($keywordsWithWindowStart as $keyword => $windowStart) {
            $keywords[$keyword] = $this->makeKeyword($user, $keyword, ['search_window_start' => $windowStart]);
        }

        return [$user, $keywords];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function queuedRun(User $user, array $attributes = []): ThreadsSearchRun
    {
        return ThreadsSearchRun::factory()->for($user)->create([
            'trigger' => ThreadsSearchRunTrigger::Scheduled,
            'queued_at' => now(),
            'started_at' => null,
            'finished_at' => null,
            'queries_sent' => 0,
            'posts_stored' => 0,
            'outcome' => null,
            ...$attributes,
        ]);
    }

    private function finishedRun(User $user, \DateTimeInterface $startedAt, int $queriesSent): ThreadsSearchRun
    {
        return ThreadsSearchRun::factory()->for($user)->create([
            'queued_at' => $startedAt,
            'started_at' => $startedAt,
            'finished_at' => $startedAt,
            'queries_sent' => $queriesSent,
        ]);
    }

    private function execute(ThreadsSearchRun $run): void
    {
        RunThreadsSearch::dispatchSync($run->id);
    }

    private function arrangeScenario(User $user, string $scenario): void
    {
        match ($scenario) {
            'completed' => $this->fakeKeywordSearch(['laravel' => $this->threadsSearchPage([$this->threadsItem('1'), $this->threadsItem('2')])]),
            'partial' => $this->fakeKeywordSearch([
                'laravel' => Http::response([], 500),
                'php' => $this->threadsSearchPage([$this->threadsItem('1')]),
            ]),
            'paused_quota' => $this->fakeKeywordSearch(['laravel' => $this->threadsError(4, 'Application request limit reached')]),
            'budget_reached' => $this->arrangeBudgetUsed($user),
            'stopped_disconnected' => $this->arrangeDisconnected($user),
        };
    }

    private function arrangeBudgetUsed(User $user): void
    {
        $this->fakeKeywordSearch();
        $this->finishedRun($user, now()->subHours(2), 2000);
    }

    private function arrangeDisconnected(User $user): void
    {
        $this->fakeKeywordSearch();
        $user->threadsConnection()->update(['access_token' => null, 'status' => ThreadsConnectionStatus::Disconnected->value, 'disconnect_reason' => ThreadsDisconnectReason::User->value]);
    }
}
