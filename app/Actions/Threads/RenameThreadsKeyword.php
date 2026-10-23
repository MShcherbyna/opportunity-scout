<?php

namespace App\Actions\Threads;

use App\Models\ThreadsKeyword;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class RenameThreadsKeyword
{
    /**
     * Rename one of the user's keywords and restart its search window.
     *
     * @throws ValidationException when the user already has the keyword
     */
    public function handle(User $user, int $keywordId, string $keyword): ThreadsKeyword
    {
        $model = $user->threadsKeywords()->findOrFail($keywordId);

        try {
            $model->update([
                'keyword' => $keyword,
                'search_window_start' => now()->subDays($user->first_search_lookback_days),
                'last_searched_at' => null,
                'last_result_count' => null,
                'zero_results_since' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['keyword' => 'This keyword already exists.']);
        }

        return $model;
    }
}
