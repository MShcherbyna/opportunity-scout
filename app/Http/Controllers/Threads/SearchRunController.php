<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Threads\StartManualSearchRun;
use App\Enums\ManualSearchRunOutcome;
use App\Http\Controllers\Controller;
use App\Services\Threads\ClientTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchRunController extends Controller
{
    /**
     * Queue a manual search run.
     */
    public function store(
        Request $request,
        StartManualSearchRun $startManualSearchRun,
        ClientTimezone $clientTimezone,
    ): RedirectResponse {
        $result = $startManualSearchRun->handle($request->user());

        Inertia::flash('toast', ManualSearchRunOutcome::fromResult($result)->toast($result['block'], $clientTimezone->fromRequest($request)));

        return back(303);
    }
}
