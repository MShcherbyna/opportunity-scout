<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Threads\UpdateSearchSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Threads\UpdateSearchSettingsRequest;
use App\Http\Resources\SearchBudgetResource;
use App\Http\Resources\SearchSettingsResource;
use App\Http\Resources\ThreadsKeywordResource;
use App\Services\Threads\SearchBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScrapingSettingsController extends Controller
{
    /**
     * Show the Scraping settings tab.
     */
    public function show(Request $request, SearchBudget $budget): Response
    {
        $user = $request->user();
        $keywords = $user->threadsKeywords()->orderBy('id')->get();

        return Inertia::render('scout-settings/Scraping', [
            'keywords' => ThreadsKeywordResource::collection($keywords)->resolve($request),
            'searchSettings' => (new SearchSettingsResource($user))->resolve($request),
            'searchBudget' => (new SearchBudgetResource($budget->plan($user->searchInterval(), $keywords->count())))->resolve($request),
            'ownPostsOnly' => (bool) config('services.threads.own_posts_only'),
        ]);
    }

    /**
     * Update the search interval and first search lookback.
     */
    public function update(UpdateSearchSettingsRequest $request, UpdateSearchSettings $updateSearchSettings): RedirectResponse
    {
        $updateSearchSettings->handle(
            $request->user(),
            (int) $request->validated('interval_minutes'),
            (int) $request->validated('first_search_lookback_days'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Search settings saved.']);

        return back(303);
    }
}
