<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsSearchRunTrigger;
use App\Jobs\RunThreadsSearch;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class ManualSearchRunTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureThreads();
    }

    /**
     * SPEC-01 AC-71, AC-46: "Run scout" queues a manual run and flashes T-10; nothing runs in the request.
     */
    public function test_run_scout_queues_a_manual_run_and_flashes_search_started(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        Queue::fake([RunThreadsSearch::class]);
        $this->fakeThreads();
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');

        $response = $this->actingAs($user)->from(route('dashboard'))->post(route('searchRuns.store'));

        $response->assertRedirect(route('dashboard'));
        $response->assertInertiaFlash('toast.type', 'success');
        $response->assertInertiaFlash('toast.message', 'Search started.');
        $run = $user->threadsSearchRuns()->firstOrFail();
        $this->assertSame(ThreadsSearchRunTrigger::Manual, $run->trigger);
        $this->assertSame('2026-10-23 12:00:00', $run->queued_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($run->started_at);
        $this->assertNull($run->finished_at);
        Queue::assertPushed(RunThreadsSearch::class, fn (RunThreadsSearch $job) => $job->searchRunId === $run->id);
    }

    /**
     * SPEC-01 AC-46: the request itself never calls Threads.
     */
    public function test_run_scout_never_calls_threads_inside_the_request(): void
    {
        Queue::fake([RunThreadsSearch::class]);
        $this->fakeThreads();
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');

        $this->actingAs($user)->post(route('searchRuns.store'));

        Http::assertNothingSent();
        Queue::assertPushed(RunThreadsSearch::class, 1);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusals(): array
    {
        return [
            'M-1 not connected' => ['not_connected', 'Connect Threads to run a search.'],
            'M-2 no keywords' => ['no_keywords', 'Add keywords to run a search.'],
            'M-3 run in progress' => ['run_in_progress', 'A search is already running.'],
            'M-5 paused' => ['paused', 'Search is paused until 13:00.'],
            'M-6 budget' => ['budget', "Not enough searches left in today's budget."],
        ];
    }

    /**
     * SPEC-01 AC-72, AC-47, AC-73: each refusal flashes its reason, queues nothing and creates no run.
     */
    #[DataProvider('refusals')]
    public function test_run_scout_is_refused_with_the_matching_reason(string $reason, string $message): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        Queue::fake([RunThreadsSearch::class]);
        $user = User::factory()->create();
        $this->arrangeManualRunBlocker($user, $reason);
        $runsBefore = ThreadsSearchRun::query()->count();

        $response = $this->actingAs($user)->from(route('posts.index'))->post(route('searchRuns.store'));

        $response->assertRedirect(route('posts.index'));
        $response->assertInertiaFlash('toast.type', 'error');
        $response->assertInertiaFlash('toast.message', $message);
        $this->assertSame($runsBefore, ThreadsSearchRun::query()->count());
        Queue::assertNotPushed(RunThreadsSearch::class);
    }

    /**
     * SPEC-01 AC-71, AC-72 (D-29): a manual run is never refused for recency.
     */
    public function test_a_manual_run_can_start_right_after_the_previous_manual_run(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        Queue::fake([RunThreadsSearch::class]);
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');

        $first = $this->actingAs($user)->post(route('searchRuns.store'));
        $first->assertInertiaFlash('toast.message', 'Search started.');
        $firstRun = $user->threadsSearchRuns()->firstOrFail();
        $firstRun->update(['started_at' => now(), 'finished_at' => now()]);

        $second = $this->actingAs($user)->post(route('searchRuns.store'));

        $second->assertInertiaFlash('toast.message', 'Search started.');
        $this->assertSame(2, $user->threadsSearchRuns()->count());
        Queue::assertPushed(RunThreadsSearch::class, 2);
    }

    /**
     * SPEC-01 NFR-12: more than 10 manual-run requests per minute get HTTP 429.
     */
    public function test_manual_runs_are_throttled_per_user(): void
    {
        Queue::fake([RunThreadsSearch::class]);
        $user = User::factory()->create();
        $statuses = [];

        foreach (range(1, 10) as $ignored) {
            $statuses[] = $this->actingAs($user)->post(route('searchRuns.store'))->getStatusCode();
        }

        $this->assertNotContains(429, $statuses);
        $this->actingAs($user)->post(route('searchRuns.store'))->assertTooManyRequests();
    }

    /**
     * SPEC-01 NFR-6: guests cannot start a run.
     */
    public function test_guests_cannot_start_a_run(): void
    {
        $this->post(route('searchRuns.store'))->assertRedirect(route('login'));
    }
}
