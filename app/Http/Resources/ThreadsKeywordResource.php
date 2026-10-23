<?php

namespace App\Http\Resources;

use App\Models\ThreadsKeyword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThreadsKeyword
 */
class ThreadsKeywordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'keyword' => $this->keyword,
            'lastSearchedAt' => $this->last_searched_at?->toIso8601String(),
            'lastResultCount' => $this->last_result_count,
            'zeroResultsSince' => $this->zero_results_since?->toIso8601String(),
        ];
    }
}
