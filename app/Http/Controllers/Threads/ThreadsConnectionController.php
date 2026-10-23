<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Threads\CompleteThreadsAuthorization;
use App\Actions\Threads\DisconnectThreadsAccount;
use App\Http\Controllers\Controller;
use App\Http\Resources\ThreadsConnectionResource;
use App\Services\Threads\ThreadsOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ThreadsConnectionController extends Controller
{
    /**
     * Show the Threads settings tab.
     */
    public function show(Request $request): Response
    {
        $connection = $request->user()->threadsConnection()->first();

        return Inertia::render('scout-settings/Threads', [
            'connection' => $connection === null ? null : (new ThreadsConnectionResource($connection))->resolve($request),
        ]);
    }

    /**
     * Redirect to the Threads authorization URL.
     */
    public function connect(Request $request, ThreadsOAuth $threadsOAuth): SymfonyResponse
    {
        return Inertia::location($threadsOAuth->authorizationUrl($request->session()));
    }

    /**
     * Handle the OAuth callback from Threads.
     */
    public function callback(Request $request, CompleteThreadsAuthorization $completeThreadsAuthorization): RedirectResponse
    {
        return $this->backToSettings($completeThreadsAuthorization->handle($request->user(), $request->session(), $request->query())->toast());
    }

    /**
     * Disconnect the user's Threads account.
     */
    public function destroy(Request $request, DisconnectThreadsAccount $disconnectThreadsAccount): RedirectResponse
    {
        return $this->backToSettings($disconnectThreadsAccount->handle($request->user())->toast());
    }

    /**
     * @param  array{type: string, message: string}  $toast
     */
    private function backToSettings(array $toast): RedirectResponse
    {
        Inertia::flash('toast', $toast);

        return to_route('settings.threads');
    }
}
