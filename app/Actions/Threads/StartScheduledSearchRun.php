<?php

namespace App\Actions\Threads;

use App\Enums\ThreadsSearchRunTrigger;
use App\Jobs\RunThreadsSearch;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use App\Services\Threads\SearchStatus;
use Illuminate\Support\Facades\DB;

class StartScheduledSearchRun
{
    public function __construct(private SearchStatus $searchStatus) {}

    /**
     * Queue a scheduled run when none is active and one is due; serialized with manual runs by the user-row lock (AC-47).
     */
    public function handle(User $user): ?ThreadsSearchRun
    {
        $run = DB::transaction(function () use ($user): ?ThreadsSearchRun {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($user->manual_only
                || $user->threadsSearchRuns()->active()->exists()
                || ! $this->searchStatus->isScheduledRunDue($user)) {
                return null;
            }

            return ThreadsSearchRun::query()->create([
                'user_id' => $user->id,
                'trigger' => ThreadsSearchRunTrigger::Scheduled,
                'queued_at' => now(),
            ]);
        });

        if ($run !== null) {
            RunThreadsSearch::dispatch($run->id);
        }

        return $run;
    }
}
