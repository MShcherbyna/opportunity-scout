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
            'block' => ['reason' => null, 'availableAt' => null, 'pausedUntil' => null],
        ]);

        $this->assertSame(ManualSearchRunOutcome::Started, $outcome);
    }

    /**
     * R8: a missing or unrecognised block reason is shown as a run in progress.
     */
    public function test_an_unknown_reason_falls_back_to_run_in_progress(): void
    {
        foreach ([null, 'something_new'] as $reason) {
            $outcome = ManualSearchRunOutcome::fromResult([
                'run' => null,
                'block' => ['reason' => $reason, 'availableAt' => null, 'pausedUntil' => null],
            ]);

            $this->assertSame(ManualSearchRunOutcome::RunInProgress, $outcome);
        }
    }

    /**
     * R8: the cooldown and pause end times are shown as clock time in the client zone.
     */
    public function test_cooldown_and_pause_times_are_formatted_in_the_client_timezone(): void
    {
        $time = Carbon::parse('2026-10-23 12:30:00', 'UTC');
        $block = ['reason' => 'cooldown', 'availableAt' => $time, 'pausedUntil' => $time];

        $cooldown = ManualSearchRunOutcome::Cooldown->toast($block, 'America/New_York');
        $paused = ManualSearchRunOutcome::Paused->toast($block, 'America/New_York');

        $this->assertSame(['type' => 'error', 'message' => 'You can run a search again at 08:30.'], $cooldown);
        $this->assertSame(['type' => 'error', 'message' => 'Search is paused until 08:30.'], $paused);
    }
}
