<?php

namespace App\Actions\Threads;

use App\Enums\ThreadsCallbackOutcome;
use App\Models\User;
use App\Services\Threads\ThreadsApiException;
use App\Services\Threads\ThreadsOAuth;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;

class CompleteThreadsAuthorization
{
    public function __construct(private readonly ConnectThreadsAccount $connectThreadsAccount) {}

    /**
     * Check the OAuth callback (state, error, code, in that order) and connect the account.
     *
     * The stored state is consumed first, so it can never be replayed.
     *
     * @param  array<string, mixed>  $query
     */
    public function handle(User $user, Session $session, array $query): ThreadsCallbackOutcome
    {
        $expectedState = $session->pull(ThreadsOAuth::STATE_SESSION_KEY);
        $state = $query['state'] ?? null;

        if (! is_string($expectedState) || $expectedState === '' || ! is_string($state) || ! hash_equals($expectedState, $state)) {
            return ThreadsCallbackOutcome::StateMismatch;
        }

        if (array_key_exists('error', $query)) {
            return ThreadsCallbackOutcome::AccessDenied;
        }

        $code = $query['code'] ?? null;

        if (! is_string($code) || $code === '' || strlen($code) > 2048) {
            return ThreadsCallbackOutcome::ConnectFailed;
        }

        try {
            $connection = $this->connectThreadsAccount->handle($user, $code);
        } catch (ThreadsApiException $exception) {
            Log::warning('Threads account connection failed.', [
                'user_id' => $user->id,
                'kind' => $exception->kind->value,
                'http_status' => $exception->httpStatus,
                'threads_code' => $exception->threadsCode,
                'body' => $exception->sanitizedBody,
            ]);

            return ThreadsCallbackOutcome::ConnectFailed;
        }

        return $connection === null ? ThreadsCallbackOutcome::AccountTaken : ThreadsCallbackOutcome::Connected;
    }
}
