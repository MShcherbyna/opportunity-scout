<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchBudgetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{dailyBudget: int, plannedDailyQueries: int, runsPerDay: int, keywordCap: int, keywordCount: int}
     */
    public function toArray(Request $request): array
    {
        /** @var array{dailyBudget: int, plannedDailyQueries: int, runsPerDay: int, keywordCap: int, keywordCount: int} $plan */
        $plan = $this->resource;

        return [
            'dailyBudget' => $plan['dailyBudget'],
            'plannedDailyQueries' => $plan['plannedDailyQueries'],
            'runsPerDay' => $plan['runsPerDay'],
            'keywordCap' => $plan['keywordCap'],
            'keywordCount' => $plan['keywordCount'],
        ];
    }
}
