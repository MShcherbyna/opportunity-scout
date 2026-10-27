<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class SearchSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{intervalMinutes: int, firstSearchLookbackDays: int, manualOnly: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'intervalMinutes' => $this->searchInterval()->value,
            'firstSearchLookbackDays' => (int) $this->first_search_lookback_days,
            'manualOnly' => (bool) $this->manual_only,
        ];
    }
}
