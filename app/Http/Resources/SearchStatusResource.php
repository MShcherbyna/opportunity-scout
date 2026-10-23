<?php

namespace App\Http\Resources;

use App\Models\ThreadsSearchRun;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
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
    public function toArray(Request $request): array
    {
        /** @var array{runState: string, lastRun: ThreadsSearchRun|null, nextRunAt: CarbonInterface|null, pausedUntil: CarbonInterface|null, block: array{reason: string|null, availableAt: CarbonInterface|null, pausedUntil: CarbonInterface|null}} $status */
        $status = $this->resource;
        $lastRun = $status['lastRun'];

        return [
            'runState' => $status['runState'],
            'lastRunId' => $lastRun?->id,
            'lastRunFinishedAt' => $lastRun?->finished_at?->toIso8601String(),
            'lastRunPostsStored' => $lastRun?->posts_stored,
            'lastRunOutcome' => $lastRun?->outcome?->value,
            'nextRunAt' => $status['nextRunAt']?->toIso8601String(),
            'pausedUntil' => $status['pausedUntil']?->toIso8601String(),
            'manualRunBlockedReason' => $status['block']['reason'],
            'manualRunAvailableAt' => $status['block']['availableAt']?->toIso8601String(),
        ];
    }
}
