<?php

namespace App\Http\Controllers\Threads;

use App\Actions\Threads\ConnectThreadsAccount;
use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Http\Controllers\Controller;
use App\Services\Threads\ThreadsApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ThreadsConnectionController extends Controller
{
    private const STATE_SESSION_KEY = 'threads_oauth_state';

    /**
     * Show the Threads settings tab.
     */
    public function show(Request $request): Response
    {
        $connection = $request->user()->threadsConnection()->first();

        return Inertia::render('scout-settings/Threads', [
            'connection' => $connection === null ? null : [
                'username' => $connection->username,
                'name' => $connection->name,
                'status' => $connection->status->value,
                'disconnectReason' => $connection->disconnect_reason?->value,
                'connectedAt' => $connection->connected_at->toIso8601String(),
                'tokenExpiresAt' => $connection->token_expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Redirect to the Threads authorization URL.
     */
    public function connect(Request $request): SymfonyResponse
    {
        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        $url = config('services.threads.authorize_url').'?'.http_build_query([
            'client_id' => config('services.threads.client_id'),
            'redirect_uri' => config('services.threads.redirect_uri'),
            'scope' => 'threads_basic,threads_keyword_search',
            'response_type' => 'code',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return Inertia::location($url);
    }

    /**
     * Handle the OAuth callback from Threads.
     */
    public function callback(Request $request, ConnectThreadsAccount $connectThreadsAccount): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $state = $request->query('state');

        if (! is_string($expectedState) || $expectedState === '' || ! is_string($state) || ! hash_equals($expectedState, $state)) {
            return $this->backToSettings('error', "We couldn't verify the Threads connection. Please try again.");
        }

        if ($request->query->has('error')) {
            return $this->backToSettings('error', 'Threads access was not granted.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '' || strlen($code) > 2048) {
            return $this->backToSettings('error', 'Connecting Threads failed. Please try again.');
        }

        try {
            $connection = $connectThreadsAccount->handle($request->user(), $code);
        } catch (ThreadsApiException) {
            return $this->backToSettings('error', 'Connecting Threads failed. Please try again.');
        }

        if ($connection === null) {
            return $this->backToSettings('error', 'This Threads account is already connected to another Opportunity Scout account.');
        }

        return $this->backToSettings('success', 'Threads account connected.');
    }

    /**
     * Disconnect the user's Threads account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $connection = $request->user()->threadsConnection()->first();

        if ($connection === null || $connection->status !== ThreadsConnectionStatus::Connected) {
            return $this->backToSettings('info', 'Threads is not connected.');
        }

        $connection->markDisconnected(ThreadsDisconnectReason::User);

        return $this->backToSettings('success', 'Threads account disconnected.');
    }

    private function backToSettings(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('settings.threads');
    }
}
