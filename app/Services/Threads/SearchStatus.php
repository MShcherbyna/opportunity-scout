<?php

namespace App\Services\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsSearchRunTrigger;
use App\Models\User;
use Carbon\CarbonInterface;

class SearchStatus
{
    public const MANUAL_RUN_COOLDOWN_MINUTES = 15;

    public function __construct(private SearchBudget $budget) {}

    /**
     * Build the `searchStatus` prop for the user.
     *
     * @return array{
     *     runState: string,
     *     lastRunId: int|null,
     *     lastRunFinishedAt: string|null,
     *     lastRunPostsStored: int|null,
     *     lastRunOutcome: string|null,
     *     nextRunAt: string|null,
     *     pausedUntil: string|null,
     *     manualRunBlockedReason: string|null,
     *     manualRunAvailableAt: string|null
     * }
     */
    public function for(User $user): array
    {
        $activeRun = $user->threadsSearchRuns()->active()->latest('id')->first();
        $lastRun = $user->threadsSearchRuns()
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->first();
        $pausedUntil = $this->pausedUntil($user);
        $block = $this->manualRunBlock($user);

        return [
            'runState' => match (true) {
                $activeRun === null => 'idle',
                $activeRun->isQueued() => 'queued',
                default => 'running',
            },
            'lastRunId' => $lastRun?->id,
            'lastRunFinishedAt' => $lastRun?->finished_at?->toIso8601String(),
            'lastRunPostsStored' => $lastRun?->posts_stored,
            'lastRunOutcome' => $lastRun?->outcome?->value,
            'nextRunAt' => $this->nextRunAt($user, $pausedUntil)?->toIso8601String(),
            'pausedUntil' => $pausedUntil?->toIso8601String(),
            'manualRunBlockedReason' => $block['reason'],
            'manualRunAvailableAt' => $block['availableAt']?->toIso8601String(),
        ];
    }

    /**
     * Why a manual run is refused right now (M-1..M-6, checked in that order), or null when it may start.
     *
     * @return array{reason: string|null, availableAt: CarbonInterface|null, pausedUntil: CarbonInterface|null}
     */
    public function manualRunBlock(User $user): array
    {
        $connection = $user->threadsConnection()->first();

        if ($connection === null || $connection->status !== ThreadsConnectionStatus::Connected) {
            return $this->block('not_connected');
        }

        $keywordCount = $user->threadsKeywords()->count();

        if ($keywordCount === 0) {
            return $this->block('no_keywords');
        }

        if ($user->threadsSearchRuns()->active()->exists()) {
            return $this->block('run_in_progress');
        }

        $lastManualRun = $user->threadsSearchRuns()
            ->where('trigger', ThreadsSearchRunTrigger::Manual)
            ->where('queued_at', '>', now()->subMinutes(self::MANUAL_RUN_COOLDOWN_MINUTES))
            ->latest('queued_at')
            ->first();

        if ($lastManualRun !== null) {
            return $this->block('cooldown', availableAt: $lastManualRun->queued_at->copy()->addMinutes(self::MANUAL_RUN_COOLDOWN_MINUTES));
        }

        $pausedUntil = $this->pausedUntil($user);

        if ($pausedUntil !== null) {
            return $this->block('paused', pausedUntil: $pausedUntil);
        }

        if ($this->budget->remainingQueries($user) < $keywordCount) {
            return $this->block('budget');
        }

        return $this->block(null);
    }

    /**
     * @return array{reason: string|null, availableAt: CarbonInterface|null, pausedUntil: CarbonInterface|null}
     */
    private function block(?string $reason, ?CarbonInterface $availableAt = null, ?CarbonInterface $pausedUntil = null): array
    {
        return ['reason' => $reason, 'availableAt' => $availableAt, 'pausedUntil' => $pausedUntil];
    }

    /**
     * The end of the current rate-limit pause, or null when search is not paused.
     */
    public function pausedUntil(User $user): ?CarbonInterface
    {
        $pausedUntil = $user->threadsConnection()->first()?->search_paused_until;

        return $pausedUntil !== null && $pausedUntil->isFuture() ? $pausedUntil : null;
    }

    private function nextRunAt(User $user, ?CarbonInterface $pausedUntil): ?CarbonInterface
    {
        $connection = $user->threadsConnection()->first();

        if ($connection === null
            || $connection->status !== ThreadsConnectionStatus::Connected
            || $pausedUntil !== null
            || ! $user->threadsKeywords()->exists()) {
            return null;
        }

        $next = $this->nextScheduledRunAt($user);

        return $next === null || $next->isPast() ? now() : $next;
    }

    /**
     * When the next scheduled run is due, or null when no scheduled run was ever queued (due immediately).
     */
    public function nextScheduledRunAt(User $user): ?CarbonInterface
    {
        $lastScheduledRun = $user->threadsSearchRuns()
            ->where('trigger', ThreadsSearchRunTrigger::Scheduled)
            ->latest('queued_at')
            ->first();

        return $lastScheduledRun?->queued_at->copy()->addMinutes($user->searchInterval()->value);
    }

    /**
     * Whether the user's scheduled run is due now.
     */
    public function isScheduledRunDue(User $user): bool
    {
        $next = $this->nextScheduledRunAt($user);

        return $next === null || $next->lessThanOrEqualTo(now());
    }
}
