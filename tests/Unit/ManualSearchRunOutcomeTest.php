<?php

namespace Tests\Unit;

use App\Enums\ManualSearchRunOutcome;
use App\Models\ThreadsSearchRun;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ManualSearchRunOutcomeTest extends TestCase
{
    /**
     * R8: a result that holds a queued run is a started search.
     */
    public function test_a_queued_run_maps_to_started(): void
    {
        $outcome = ManualSearchRunOutcome::fromResult([
            'run' => new ThreadsSearchRun,
            'block' => ['reason' => null, 'pausedUntil' => null],
        ]);

        $this->assertSame(ManualSearchRunOutcome::Started, $outcome);
    }

    /**
     * R8: a missing or unrecognised block reason (including the retired `cooldown` value) is shown as a run in progress.
     */
    public function test_an_unknown_reason_falls_back_to_run_in_progress(): void
    {
        foreach ([null, 'something_new', 'cooldown'] as $reason) {
            $outcome = ManualSearchRunOutcome::fromResult([
                'run' => null,
                'block' => ['reason' => $reason, 'pausedUntil' => null],
            ]);

            $this->assertSame(ManualSearchRunOutcome::RunInProgress, $outcome);
        }
    }

    /**
     * R8: the pause end time is shown as clock time in the client zone.
     */
    public function test_pause_time_is_formatted_in_the_client_timezone(): void
    {
        $time = Carbon::parse('2026-10-23 12:30:00', 'UTC');
        $block = ['reason' => 'paused', 'pausedUntil' => $time];

        $paused = ManualSearchRunOutcome::Paused->toast($block, 'America/New_York');

        $this->assertSame(['type' => 'error', 'message' => 'Search is paused until 08:30.'], $paused);
    }
}
