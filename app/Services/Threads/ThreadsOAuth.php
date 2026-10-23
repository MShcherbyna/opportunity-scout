<?php

namespace App\Services\Threads;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

class ThreadsOAuth
{
    public const STATE_SESSION_KEY = 'threads_oauth_state';

    /**
     * Start an authorization: remember a fresh state in the session and build the Threads authorize URL.
     */
    public function authorizationUrl(Session $session): string
    {
        $state = Str::random(40);
        $session->put(self::STATE_SESSION_KEY, $state);

        return config('services.threads.authorize_url').'?'.http_build_query([
            'client_id' => config('services.threads.client_id'),
            'redirect_uri' => config('services.threads.redirect_uri'),
            'scope' => 'threads_basic,threads_keyword_search',
            'response_type' => 'code',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
