<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Threads\AddThreadsKeyword;
use App\Actions\Threads\RenameThreadsKeyword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Threads\StoreThreadsKeywordRequest;
use App\Http\Requests\Threads\UpdateThreadsKeywordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ThreadsKeywordController extends Controller
{
    /**
     * Add a keyword.
     */
    public function store(StoreThreadsKeywordRequest $request, AddThreadsKeyword $addThreadsKeyword): RedirectResponse
    {
        $addThreadsKeyword->handle($request->user(), $request->validated('keyword'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Keyword added.']);

        return back(303);
    }

    /**
     * Rename a keyword.
     */
    public function update(UpdateThreadsKeywordRequest $request, RenameThreadsKeyword $renameThreadsKeyword, int $keyword): RedirectResponse
    {
        $renameThreadsKeyword->handle($request->user(), $keyword, $request->validated('keyword'));

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
}
