<?php

namespace Tests\Feature\Threads;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ThreadsScheduleTest extends TestCase
{
    /**
     * SPEC-01 AC-26, AC-66, MC-7: both daily commands run once a day, without overlapping, on one server.
     */
    public function test_refresh_and_prune_commands_run_daily_without_overlapping_on_one_server(): void
    {
        foreach (['threads:refresh-tokens', 'threads:prune-posts'] as $command) {
            $event = $this->scheduledEvent($command);

            $this->assertSame('0 0 * * *', $event->expression, $command);
            $this->assertTrue($event->withoutOverlapping, $command);
            $this->assertTrue($event->onOneServer, $command);
        }
    }

    /**
     * SPEC-01 MC-7, plan section 3: the dispatcher runs every minute, without overlapping, on one server.
     */
    public function test_search_dispatcher_runs_every_minute_without_overlapping_on_one_server(): void
    {
        $event = $this->scheduledEvent('threads:dispatch-search-runs');

        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    private function scheduledEvent(string $command): Event
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains((string) $event->command, $command));

        $this->assertCount(1, $events, "Expected exactly one schedule entry for {$command}.");

        return $events->first();
    }
}
