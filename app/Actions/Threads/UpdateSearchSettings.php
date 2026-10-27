<?php

namespace App\Actions\Threads;

use App\Models\User;

class UpdateSearchSettings
{
    /**
     * Store the user's search interval, first search lookback, and manual-only preference.
     */
    public function handle(User $user, int $intervalMinutes, int $firstSearchLookbackDays, bool $manualOnly): void
    {
        $user->update([
            'search_interval_minutes' => $intervalMinutes,
            'first_search_lookback_days' => $firstSearchLookbackDays,
            'manual_only' => $manualOnly,
        ]);
    }
}
