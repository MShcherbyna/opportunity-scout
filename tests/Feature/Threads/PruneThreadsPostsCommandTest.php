<?php

namespace Tests\Feature\Threads;

use App\Models\ThreadsSearchRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class PruneThreadsPostsCommandTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-23 12:00:00');
    }

    /**
     * SPEC-01 AC-66, D-4: posts found more than 90 days ago are deleted; the post time does not matter.
     */
    public function test_deletes_posts_found_more_than_90_days_ago_by_found_time(): void
    {
        $user = User::factory()->create();
        $oldFound = $this->makePost($user, ['created_at' => now()->subDays(91), 'posted_at' => now()->subDays(1)]);
        $recentFound = $this->makePost($user, ['created_at' => now()->subDays(89), 'posted_at' => now()->subDays(200)]);

        Artisan::call('threads:prune-posts');

        $this->assertModelMissing($oldFound);
        $this->assertModelExists($recentFound);
    }

    /**
     * SPEC-01 plan R4: search runs older than 7 days are pruned; the last 24 hours and the last week stay.
     */
    public function test_deletes_search_runs_older_than_seven_days_and_keeps_recent_ones(): void
    {
        $user = User::factory()->create();
        $old = ThreadsSearchRun::factory()->for($user)->create(['queued_at' => now()->subDays(8)]);
        $recent = ThreadsSearchRun::factory()->for($user)->create(['queued_at' => now()->subDays(6)]);
        $today = ThreadsSearchRun::factory()->for($user)->create(['queued_at' => now()->subHour()]);

        Artisan::call('threads:prune-posts');

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($today);
    }

    /**
     * SPEC-01 NFR-10: one log entry reports how many posts and runs were deleted.
     */
    public function test_writes_one_log_entry_with_the_counts(): void
    {
        $logs = $this->captureLogs();
        $user = User::factory()->create();
        $this->makePost($user, ['created_at' => now()->subDays(100)]);
        $this->makePost($user, ['created_at' => now()->subDays(95)]);
        foreach ([8, 9, 10] as $days) {
            ThreadsSearchRun::factory()->for($user)->create(['queued_at' => now()->subDays($days)]);
        }

        Artisan::call('threads:prune-posts');

        $entries = $this->logsAt($logs, 'info');
        $this->assertCount(1, $entries);
        $values = $this->logValues($entries[0]);
        $this->assertContains('2', $values);
        $this->assertContains('3', $values);
    }
}
