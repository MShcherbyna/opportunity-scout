<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoundActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{date: string, found: int}
     */
    public function toArray(Request $request): array
    {
        /** @var array{date: string, found: int} $day */
        $day = $this->resource;

        return [
            'date' => $day['date'],
            'found' => $day['found'],
        ];
    }
}
