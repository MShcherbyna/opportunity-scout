<?php

namespace App\Console\Commands;

use App\Actions\Threads\StartScheduledSearchRun;
use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsSearchRunOutcome;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('threads:dispatch-search-runs')]
#[Description('Queue due Threads keyword search runs')]
class DispatchThreadsSearchRuns extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(StartScheduledSearchRun $startScheduledSearchRun): int
    {
        ThreadsSearchRun::query()->stale()->update([
            'finished_at' => now(),
            'outcome' => ThreadsSearchRunOutcome::Partial->value,
        ]);

        User::query()
            ->whereHas('threadsConnection', function ($query): void {
                $query->where('status', ThreadsConnectionStatus::Connected)
                    ->where(function ($query): void {
                        $query->whereNull('search_paused_until')->orWhere('search_paused_until', '<=', now());
                    });
            })
            ->whereHas('threadsKeywords')
            ->where('manual_only', false)
            ->whereDoesntHave('threadsSearchRuns', fn ($query) => $query->active())
            ->lazyById()
            ->each(fn (User $user) => $startScheduledSearchRun->handle($user));

        return self::SUCCESS;
    }
}
