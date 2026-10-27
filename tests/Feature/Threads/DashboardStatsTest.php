<?php

namespace Tests\Feature\Threads;

use App\Models\User;
use App\Services\Threads\DashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    private const TIMEZONE = 'America/New_York';

    /**
     * R8: activity is 30 days oldest first, days start at midnight in the client zone, and only the user's posts in the window count.
     */
    public function test_activity_counts_posts_per_client_day_for_the_last_30_days(): void
    {
        // 12:00 UTC is 08:00 on 2026-10-23 in New York (UTC-4), so client midnight is 04:00 UTC.
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->makeFoundPost($user, '2026-10-23 03:59:59', isRelevant: true);
        $this->makeFoundPost($user, '2026-10-23 04:00:00', isRelevant: true);
        $this->makeFoundPost($user, '2026-10-23 04:00:00');
        $this->makeFoundPost($user, '2026-09-24 04:00:00');
        $this->makeFoundPost($user, '2026-09-24 03:59:59');
        $this->makeFoundPost($other, '2026-10-23 05:00:00');

        $activity = app(DashboardStats::class)->activity($user, self::TIMEZONE);

        $this->assertCount(30, $activity);
        $this->assertSame('2026-09-24', $activity[0]['date']);
        $this->assertSame('2026-10-23', $activity[29]['date']);
        $this->assertSame(1, $activity[0]['found']);
        $this->assertSame(1, $activity[28]['found']);
        $this->assertSame(2, $activity[29]['found']);
        $this->assertSame(4, array_sum(array_column($activity, 'found')));
        // 2026-10-23 04:00:00 is day 29 (client day); the 03:59:59 post belongs to day 28.
        $this->assertSame(1, $activity[29]['relevant']);
        $this->assertSame(1, $activity[28]['relevant']);
        $this->assertSame(2, array_sum(array_column($activity, 'relevant')));

        foreach ($activity as $day) {
            $this->assertIsInt($day['found']);
            $this->assertIsInt($day['relevant']);
        }
    }

    private function makeFoundPost(User $user, string $foundAtUtc, bool $isRelevant = false): void
    {
        $this->makePost($user, ['is_relevant' => $isRelevant])->forceFill([
            'created_at' => $foundAtUtc,
            'updated_at' => $foundAtUtc,
        ])->saveQuietly();
    }
}
