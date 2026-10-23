<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThreadsQuotaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{usedLast24Hours: int, remaining: int, dailyBudget: int, threadsDailyLimit: int}
     */
    public function toArray(Request $request): array
    {
        /** @var array{usedLast24Hours: int, remaining: int, dailyBudget: int, threadsDailyLimit: int} $usage */
        $usage = $this->resource;

        return [
            'usedLast24Hours' => $usage['usedLast24Hours'],
            'remaining' => $usage['remaining'],
            'dailyBudget' => $usage['dailyBudget'],
            'threadsDailyLimit' => $usage['threadsDailyLimit'],
        ];
    }
}
