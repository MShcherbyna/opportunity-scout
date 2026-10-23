<?php

namespace App\Http\Controllers\Threads;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardStatsResource;
use App\Http\Resources\FoundActivityResource;
use App\Http\Resources\SearchStatusResource;
use App\Http\Resources\ThreadsPostResource;
use App\Http\Resources\ThreadsQuotaResource;
use App\Services\Threads\ClientTimezone;
use App\Services\Threads\DashboardStats;
use App\Services\Threads\SearchBudget;
use App\Services\Threads\SearchStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function __invoke(
        Request $request,
        ClientTimezone $clientTimezone,
        SearchBudget $budget,
        SearchStatus $searchStatus,
        DashboardStats $dashboardStats,
    ): Response {
        $user = $request->user();
        $timezone = $clientTimezone->fromRequest($request);

        return Inertia::render('Dashboard', [
            'threadsQuota' => fn (): array => (new ThreadsQuotaResource($budget->usage($user)))->resolve($request),
            'stats' => fn (): array => (new DashboardStatsResource($dashboardStats->stats($user, $timezone)))->resolve($request),
            'activity' => fn (): array => FoundActivityResource::collection($dashboardStats->activity($user, $timezone))->resolve($request),
            'latestPosts' => fn (): array => ThreadsPostResource::collection($dashboardStats->latestPosts($user))->resolve($request),
            'searchStatus' => fn (): array => (new SearchStatusResource($searchStatus->snapshot($user)))->resolve($request),
        ]);
    }
}
