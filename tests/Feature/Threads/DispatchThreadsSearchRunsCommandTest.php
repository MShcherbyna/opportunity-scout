<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsSearchRunOutcome;
use App\Enums\ThreadsSearchRunTrigger;
use App\Jobs\RunThreadsSearch;
use App\Models\ThreadsConnection;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class DispatchThreadsSearchRunsCommandTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-23 12:00:00');
    }

    /**
     * SPEC-01 AC-45, AC-46: users that are due get a scheduled run row and a queued job; users with no run yet are due at once.
     */
    public function test_queues_a_run_for_users_due_by_their_interval(): void
    {
        Queue::fake([RunThreadsSearch::class]);
        $dueUser = $this->searchableUser(['search_interval_minutes' => 15]);
        $this->finishedScheduledRun($dueUser, queuedMinutesAgo: 16);
        $newUser = $this->searchableUser();

        Artisan::call('threads:dispatch-search-runs');

        foreach ([$dueUser, $newUser] as $user) {
            $run = $user->threadsSearchRuns()->latest('id')->firstOrFail();
            $this->assertSame(ThreadsSearchRunTrigger::Scheduled, $run->trigger);
            $this->assertSame('2026-10-23 12:00:00', $run->queued_at->utc()->format('Y-m-d H:i:s'));
            $this->assertNull($run->started_at);
            $this->assertNull($run->finished_at);
            Queue::assertPushed(RunThreadsSearch::class, fn (RunThreadsSearch $job) => $job->searchRunId === $run->id);
        }
        Queue::assertPushed(RunThreadsSearch::class, 2);
    }

    /**
     * @return array<string, array{int, int, bool}>
     */
    public static function intervals(): array
    {
        return [
            '15 minute interval, 10 minutes ago' => [15, 10, false],
            '15 minute interval, exactly 15 minutes ago' => [15, 15, true],
            '5 minute interval, 6 minutes ago' => [5, 6, true],
            '60 minute interval, 59 minutes ago' => [60, 59, false],
            '60 minute interval, exactly 60 minutes ago' => [60, 60, true],
        ];
    }

    /**
     * SPEC-01 AC-45, D-1: a run is queued only once the user's own interval has passed since the last scheduled run.
     */
    #[DataProvider('intervals')]
    public function test_does_not_queue_before_the_interval_has_passed(int $interval, int $minutesAgo, bool $queued): void
    {
        Queue::fake([RunThreadsSearch::class]);
        $user = $this->searchableUser(['search_interval_minutes' => $interval]);
        $this->finishedScheduledRun($user, queuedMinutesAgo: $minutesAgo);
        $dueControl = $this->searchableUser();

        Artisan::call('threads:dispatch-search-runs');

        $this->assertSame($queued ? 2 : 1, $user->threadsSearchRuns()->count());
        $this->assertSame(1, $dueControl->threadsSearchRuns()->count());
        Queue::assertPushed(RunThreadsSearch::class, $queued ? 2 : 1);
    }

    /**
     * SPEC-01 AC-12, AC-45, AC-73: no run for users without a connection, with a disconnected one, without keywords or while paused.
     */
    public function test_skips_users_without_connection_keywords_or_while_paused(): void
    {
        Queue::fake([RunThreadsSearch::class]);
        $noConnection = User::factory()->create();
        $this->makeKeyword($noConnection, 'laravel');
        $disconnected = User::factory()->create();
        ThreadsConnection::factory()->for($disconnected)->disconnected()->create();
        $this->makeKeyword($disconnected, 'laravel');
        $noKeywords = User::factory()->create();
        ThreadsConnection::factory()->for($noKeywords)->create();
        $paused = $this->searchableUser();
        $paused->threadsConnection()->update(['search_paused_until' => now()->addMinutes(30)]);
        $dueControl = $this->searchableUser();

        Artisan::call('threads:dispatch-search-runs');

        $this->assertDatabaseCount('threads_search_runs', 1);
        $this->assertSame(1, $dueControl->threadsSearchRuns()->count());
        Queue::assertPushed(RunThreadsSearch::class, 1);
    }

    /**
     * SPEC-01 EC-42: once the pause has ended, the next scheduled run starts normally.
     */
    public function test_queues_a_run_after_the_pause_has_ended(): void
    {
        Queue::fake([RunThreadsSearch::class]);
        $user = $this->searchableUser();
        $user->threadsConnection()->update(['search_paused_until' => now()->subMinute()]);

        Artisan::call('threads:dispatch-search-runs');

        $this->assertSame(1, $user->threadsSearchRuns()->count());
        Queue::assertPushed(RunThreadsSearch::class, 1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function activeRuns(): array
    {
        return [
            'scheduled run still queued' => ['queued_scheduled'],
            'scheduled run in progress' => ['running_scheduled'],
            'manual run still queued' => ['queued_manual'],
        ];
    }

    /**
     * SPEC-01 AC-47: no second run while one is queued or in progress, even when the interval is over.
     */
    #[DataProvider('activeRuns')]
    public function test_does_not_queue_while_a_run_is_queued_or_running(string $case): void
    {
        Queue::fake([RunThreadsSearch::class]);
        $user = $this->searchableUser(['search_interval_minutes' => 5]);
        $factory = ThreadsSearchRun::factory()->for($user);
        match ($case) {
            'queued_scheduled' => $factory->scheduled()->create(['queued_at' => now()->subMinutes(20), 'started_at' => null, 'finished_at' => null, 'outcome' => null]),
            'running_scheduled' => $factory->scheduled()->create(['queued_at' => now()->subMinutes(20), 'started_at' => now()->subMinutes(5), 'finished_at' => null, 'outcome' => null]),
            'queued_manual' => $factory->manual()->create(['queued_at' => now()->subMinutes(20), 'started_at' => null, 'finished_at' => null, 'outcome' => null]),
        };
        $dueControl = $this->searchableUser();

        Artisan::call('threads:dispatch-search-runs');

        $this->assertSame(1, $user->threadsSearchRuns()->count());
        $this->assertSame(1, $dueControl->threadsSearchRuns()->count());
        Queue::assertPushed(RunThreadsSearch::class, 1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function staleRuns(): array
    {
        return [
            'queued for 31 minutes and never started' => ['queued'],
            'started 11 minutes ago and never finished' => ['started'],
        ];
    }

    /**
     * SPEC-01 AC-47, EC-46: a run that never recorded its end is closed as `partial` and stops blocking new runs.
     */
    #[DataProvider('staleRuns')]
    public function test_closes_stale_runs_as_partial_and_queues_a_new_one(string $case): void
    {
        Queue::fake([RunThreadsSearch::class]);
        $user = $this->searchableUser(['search_interval_minutes' => 5]);
        $stale = ThreadsSearchRun::factory()->for($user)->scheduled()->create(
            $case === 'queued'
                ? ['queued_at' => now()->subMinutes(31), 'started_at' => null, 'finished_at' => null, 'outcome' => null]
                : ['queued_at' => now()->subMinutes(12), 'started_at' => now()->subMinutes(11), 'finished_at' => null, 'outcome' => null],
        );

        Artisan::call('threads:dispatch-search-runs');

        $closed = $stale->fresh();
        $this->assertSame(ThreadsSearchRunOutcome::Partial, $closed->outcome);
        $this->assertSame('2026-10-23 12:00:00', $closed->finished_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(2, $user->threadsSearchRuns()->count());
        Queue::assertPushed(RunThreadsSearch::class, 1);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function searchableUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        ThreadsConnection::factory()->for($user)->create();
        $this->makeKeyword($user, 'laravel');

        return $user;
    }

    private function finishedScheduledRun(User $user, int $queuedMinutesAgo): ThreadsSearchRun
    {
        return ThreadsSearchRun::factory()->for($user)->scheduled()->create([
            'queued_at' => now()->subMinutes($queuedMinutesAgo),
            'started_at' => now()->subMinutes($queuedMinutesAgo),
            'finished_at' => now()->subMinutes($queuedMinutesAgo),
            'outcome' => ThreadsSearchRunOutcome::Completed,
        ]);
    }
}
