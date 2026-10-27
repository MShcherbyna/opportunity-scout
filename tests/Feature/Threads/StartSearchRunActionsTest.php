<?php

namespace Tests\Feature\Threads;

use App\Actions\Threads\StartManualSearchRun;
use App\Actions\Threads\StartScheduledSearchRun;
use App\Enums\ThreadsSearchRunOutcome;
use App\Enums\ThreadsSearchRunTrigger;
use App\Jobs\RunThreadsSearch;
use App\Models\ThreadsConnection;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class StartSearchRunActionsTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureThreads();
        $this->travelTo('2026-10-23 12:00:00');
        Queue::fake([RunThreadsSearch::class]);
    }

    /**
     * SPEC-01 AC-47 (fix AR-3): a scheduled run is queued for a due user and carries the scheduled trigger.
     */
    public function test_scheduled_action_queues_a_run_for_a_due_user(): void
    {
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');

        $run = app(StartScheduledSearchRun::class)->handle($user);

        $this->assertNotNull($run);
        $this->assertSame(ThreadsSearchRunTrigger::Scheduled, $run->trigger);
        $this->assertSame(1, $user->threadsSearchRuns()->count());
        Queue::assertPushed(RunThreadsSearch::class, fn (RunThreadsSearch $job) => $job->searchRunId === $run->id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function activeRunTriggers(): array
    {
        return [
            'active manual run' => ['manual'],
            'active scheduled run' => ['scheduled'],
        ];
    }

    /**
     * SPEC-01 AC-47 (fix AR-3): while any run is active the scheduled action creates no second run and queues no job.
     */
    #[DataProvider('activeRunTriggers')]
    public function test_scheduled_action_creates_no_run_while_a_run_is_active(string $trigger): void
    {
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');
        ThreadsSearchRun::factory()->for($user)->{$trigger}()->create([
            'queued_at' => now()->subMinutes(20),
            'started_at' => null,
            'finished_at' => null,
            'outcome' => null,
        ]);

        $run = app(StartScheduledSearchRun::class)->handle($user);

        $this->assertNull($run);
        $this->assertSame(1, $user->threadsSearchRuns()->count());
        Queue::assertNothingPushed();
    }

    /**
     * SPEC-01 AC-45, D-1 (fix AR-3): a scheduled run that is not due yet is not created.
     */
    public function test_scheduled_action_creates_no_run_before_the_interval_has_passed(): void
    {
        $user = User::factory()->create(['search_interval_minutes' => 60]);
        $this->makeKeyword($user, 'laravel');
        ThreadsConnection::factory()->for($user)->create();
        ThreadsSearchRun::factory()->for($user)->scheduled()->create([
            'queued_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(9),
            'outcome' => ThreadsSearchRunOutcome::Completed,
        ]);

        $run = app(StartScheduledSearchRun::class)->handle($user);

        $this->assertNull($run);
        $this->assertSame(1, $user->threadsSearchRuns()->count());
        Queue::assertNothingPushed();
    }

    /**
     * SPEC-01 AC-47, M-3 (fix AR-3): a manual run is refused with `run_in_progress` while a scheduled run is active.
     */
    public function test_manual_action_is_refused_while_a_scheduled_run_is_active(): void
    {
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');
        ThreadsSearchRun::factory()->for($user)->scheduled()->create([
            'queued_at' => now()->subMinutes(2),
            'started_at' => now()->subMinute(),
            'finished_at' => null,
            'outcome' => null,
        ]);

        $result = app(StartManualSearchRun::class)->handle($user);

        $this->assertNull($result['run']);
        $this->assertSame('run_in_progress', $result['block']['reason']);
        $this->assertSame(1, $user->threadsSearchRuns()->count());
        Queue::assertNothingPushed();
    }

    /**
     * SPEC-01 AC-71 (fix AR-3): an allowed manual run is returned with an empty block and queued.
     */
    public function test_manual_action_returns_the_run_and_an_empty_block_when_allowed(): void
    {
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');

        $result = app(StartManualSearchRun::class)->handle($user);

        $this->assertNotNull($result['run']);
        $this->assertSame(ThreadsSearchRunTrigger::Manual, $result['run']->trigger);
        $this->assertNull($result['block']['reason']);
        Queue::assertPushed(RunThreadsSearch::class, fn (RunThreadsSearch $job) => $job->searchRunId === $result['run']->id);
    }

    /**
     * SPEC-01 AC-47 (fix AR-3): after a manual run was started, the scheduled action refuses to start another one.
     */
    public function test_scheduled_action_creates_no_run_right_after_a_manual_run_was_started(): void
    {
        $user = $this->createConnectedUser();
        $this->makeKeyword($user, 'laravel');
        $manual = app(StartManualSearchRun::class)->handle($user);

        $scheduled = app(StartScheduledSearchRun::class)->handle($user);

        $this->assertNotNull($manual['run']);
        $this->assertNull($scheduled);
        $this->assertSame(1, $user->threadsSearchRuns()->count());
        Queue::assertPushed(RunThreadsSearch::class, 1);
    }
}
