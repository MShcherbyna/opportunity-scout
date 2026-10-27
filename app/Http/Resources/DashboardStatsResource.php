<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{postsFound: int, foundLast7Days: int, foundToday: int, relevantPosts: int}
     */
    public function toArray(Request $request): array
    {
        /** @var array{postsFound: int, foundLast7Days: int, foundToday: int, relevantPosts: int} $stats */
        $stats = $this->resource;

        return [
            'postsFound' => $stats['postsFound'],
            'foundLast7Days' => $stats['foundLast7Days'],
            'foundToday' => $stats['foundToday'],
            'relevantPosts' => $stats['relevantPosts'],
        ];
    }
}
