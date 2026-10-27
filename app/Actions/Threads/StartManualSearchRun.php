<?php

namespace App\Actions\Threads;

use App\Enums\ThreadsSearchRunTrigger;
use App\Jobs\RunThreadsSearch;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use App\Services\Threads\SearchStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class StartManualSearchRun
{
    public function __construct(private SearchStatus $searchStatus) {}

    /**
     * Queue a manual run; when it is refused (M-1, M-2, M-3, M-5, M-6) the run is null and the block says why.
     *
     * @return array{run: ThreadsSearchRun|null, block: array{reason: string|null, pausedUntil: CarbonInterface|null}}
     */
    public function handle(User $user): array
    {
        $result = DB::transaction(function () use ($user): array {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $block = $this->searchStatus->manualRunBlock($user);

            if ($block['reason'] !== null) {
                return ['run' => null, 'block' => $block];
            }

            $run = ThreadsSearchRun::query()->create([
                'user_id' => $user->id,
                'trigger' => ThreadsSearchRunTrigger::Manual,
                'queued_at' => now(),
            ]);

            return ['run' => $run, 'block' => $block];
        });

        if ($result['run'] !== null) {
            RunThreadsSearch::dispatch($result['run']->id);
        }

        return $result;
    }
}
