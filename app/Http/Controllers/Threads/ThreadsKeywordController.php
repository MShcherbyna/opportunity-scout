<?php

namespace App\Http\Controllers\Threads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Threads\StoreThreadsKeywordRequest;
use App\Http\Requests\Threads\UpdateThreadsKeywordRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ThreadsKeywordController extends Controller
{
    /**
     * Add a keyword.
     */
    public function store(StoreThreadsKeywordRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $user->threadsKeywords()->create([
                'keyword' => $request->validated('keyword'),
                'search_window_start' => now()->subDays($user->first_search_lookback_days),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateKeyword();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Keyword added.']);

        return back(303);
    }

    /**
     * Rename a keyword.
     */
    public function update(UpdateThreadsKeywordRequest $request, int $keyword): RedirectResponse
    {
        $user = $request->user();

        $model = $user->threadsKeywords()->findOrFail($keyword);

        try {
            $model->update([
                'keyword' => $request->validated('keyword'),
                'search_window_start' => now()->subDays($user->first_search_lookback_days),
                'last_searched_at' => null,
                'last_result_count' => null,
                'zero_results_since' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateKeyword();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Keyword updated.']);

        return back(303);
    }

    /**
     * Delete a keyword.
     */
    public function destroy(Request $request, int $keyword): RedirectResponse
    {
        $request->user()->threadsKeywords()->findOrFail($keyword)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Keyword removed.']);

        return back(303);
    }

    private function duplicateKeyword(): ValidationException
    {
        return ValidationException::withMessages(['keyword' => 'This keyword already exists.']);
    }
}
