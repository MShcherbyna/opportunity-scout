<?php

namespace App\Actions\Threads;

use App\Models\ThreadsKeyword;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class AddThreadsKeyword
{
    /**
     * Add a keyword to the user's list.
     *
     * @throws ValidationException when the user already has the keyword
     */
    public function handle(User $user, string $keyword): ThreadsKeyword
    {
        try {
            return $user->threadsKeywords()->create([
                'keyword' => $keyword,
                'search_window_start' => now()->subDays($user->first_search_lookback_days),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['keyword' => 'This keyword already exists.']);
        }
    }
}
