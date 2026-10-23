<?php

namespace App\Actions\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Models\ThreadsConnection;
use App\Models\User;
use App\Services\Threads\ThreadsApiException;
use App\Services\Threads\ThreadsClient;
use Illuminate\Support\Facades\DB;

class ConnectThreadsAccount
{
    public function __construct(private readonly ThreadsClient $client) {}

    /**
     * Exchange the authorization code and save the connection.
     *
     * Returns null when the Threads account is `connected` to another user (nothing is saved).
     * The short-lived token stays in a local variable and is never persisted.
     * Invariant: every connect/reconnect must write a new `connected_at`, because `ThreadsConnection::isStillSameConnection()` uses it as the connection identity to stop in-flight search runs.
     *
     * @throws ThreadsApiException
     */
    public function handle(User $user, string $code): ?ThreadsConnection
    {
        $shortLived = $this->client->exchangeCode($code);
        $longLived = $this->client->exchangeForLongLivedToken($shortLived['access_token']);
        $profile = $this->client->me($longLived['access_token']);

        $now = now();

        return DB::transaction(function () use ($user, $longLived, $profile, $now): ?ThreadsConnection {
            $takenByAnotherUser = ThreadsConnection::query()
                ->where('threads_user_id', $profile['id'])
                ->where('user_id', '!=', $user->id)
                ->where('status', ThreadsConnectionStatus::Connected)
                ->lockForUpdate()
                ->exists();

            if ($takenByAnotherUser) {
                return null;
            }

            return ThreadsConnection::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'threads_user_id' => $profile['id'],
                    'username' => $profile['username'],
                    'name' => $profile['name'],
                    'access_token' => $longLived['access_token'],
                    'token_issued_at' => $now,
                    'token_expires_at' => $now->copy()->addSeconds($longLived['expires_in']),
                    'status' => ThreadsConnectionStatus::Connected,
                    'disconnect_reason' => null,
                    'disconnected_at' => null,
                    'search_paused_until' => null,
                    'connected_at' => $now,
                ],
            );
        });
    }
}
