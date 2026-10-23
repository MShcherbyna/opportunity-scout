<?php

namespace App\Http\Controllers\Threads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Threads\UpdateSearchSettingsRequest;
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
    public function show(Request $request): Response
    {
        $user = $request->user();
        $interval = $user->searchInterval();
        $keywords = $user->threadsKeywords()->orderBy('id')->get();

        return Inertia::render('scout-settings/Scraping', [
            'keywords' => $keywords->map(fn ($keyword): array => [
                'id' => $keyword->id,
                'keyword' => $keyword->keyword,
                'lastSearchedAt' => $keyword->last_searched_at?->toIso8601String(),
                'lastResultCount' => $keyword->last_result_count,
                'zeroResultsSince' => $keyword->zero_results_since?->toIso8601String(),
            ])->all(),
            'searchSettings' => [
                'intervalMinutes' => $interval->value,
                'firstSearchLookbackDays' => (int) $user->first_search_lookback_days,
            ],
            'searchBudget' => [
                'dailyBudget' => SearchBudget::DAILY_BUDGET,
                'plannedDailyQueries' => $keywords->count() * $interval->runsPerDay(),
                'runsPerDay' => $interval->runsPerDay(),
                'keywordCap' => $interval->keywordCap(),
                'keywordCount' => $keywords->count(),
            ],
            'ownPostsOnly' => (bool) config('services.threads.own_posts_only'),
        ]);
    }

    /**
     * Update the search interval and first search lookback.
     */
    public function update(UpdateSearchSettingsRequest $request): RedirectResponse
    {
        $request->user()->update([
            'search_interval_minutes' => (int) $request->validated('interval_minutes'),
            'first_search_lookback_days' => (int) $request->validated('first_search_lookback_days'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Search settings saved.']);

        return back(303);
    }
}
