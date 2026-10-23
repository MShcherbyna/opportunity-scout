<?php

namespace App\Jobs;

use App\Actions\Threads\ExecuteThreadsSearchRun;
use App\Enums\ThreadsSearchRunOutcome;
use App\Models\ThreadsSearchRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunThreadsSearch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(public int $searchRunId) {}

    /**
     * Execute the job.
     */
    public function handle(ExecuteThreadsSearchRun $executeThreadsSearchRun): void
    {
        $run = ThreadsSearchRun::query()->find($this->searchRunId);

        if ($run === null) {
            return;
        }

        $executeThreadsSearchRun->handle($run);
    }

    /**
     * Close an unfinished run as partial when the job fails.
     */
    public function failed(?Throwable $exception = null): void
    {
        $run = ThreadsSearchRun::query()->find($this->searchRunId);

        if ($run === null || $run->finished_at !== null) {
            return;
        }

        $run->forceFill([
            'finished_at' => now(),
            'outcome' => ThreadsSearchRunOutcome::Partial,
        ])->save();

        Log::error('Threads search job failed.', [
            'run_id' => $run->id,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
