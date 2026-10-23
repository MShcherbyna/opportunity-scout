<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Threads\StartManualSearchRun;
use App\Http\Controllers\Controller;
use App\Services\Threads\ClientTimezone;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $user = $request->user();

        $result = $startManualSearchRun->handle($user);

        if ($result['run'] !== null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Search started.']);

            return back(303);
        }

        $block = $result['block'];
        $timezone = $clientTimezone->fromRequest($request);
        $format = fn (?DateTimeInterface $time): string => $time === null
            ? ''
            : Carbon::instance($time)->setTimezone($timezone)->format('H:i');

        $message = match ($block['reason']) {
            'not_connected' => 'Connect Threads to run a search.',
            'no_keywords' => 'Add keywords to run a search.',
            'cooldown' => 'You can run a search again at '.$format($block['availableAt']).'.',
            'paused' => 'Search is paused until '.$format($block['pausedUntil']).'.',
            'budget' => "Not enough searches left in today's budget.",
            default => 'A search is already running.',
        };

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back(303);
    }
}
