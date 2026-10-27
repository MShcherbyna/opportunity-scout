<?php

namespace App\Actions\Threads;

use App\Models\User;

class UpdateSearchSettings
{
    /**
     * Store the user's search interval and first search lookback.
     */
    public function handle(User $user, int $intervalMinutes, int $firstSearchLookbackDays): void
    {
        $user->update([
            'search_interval_minutes' => $intervalMinutes,
            'first_search_lookback_days' => $firstSearchLookbackDays,
        ]);
    }
}
