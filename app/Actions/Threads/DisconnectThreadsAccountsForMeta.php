<?php

namespace App\Actions\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use Illuminate\Support\Facades\Log;

class DisconnectThreadsAccountsForMeta
{
    /**
     * Disconnect every connected account with the given Threads user id (idempotent) and return how many changed.
     */
    public function handle(string $threadsUserId): int
    {
        $matched = ThreadsConnection::query()
            ->where('threads_user_id', $threadsUserId)
            ->where('status', ThreadsConnectionStatus::Connected)
            ->update(ThreadsConnection::disconnectedAttributes(ThreadsDisconnectReason::Uninstalled));

        if ($matched === 0 && ! ThreadsConnection::query()->where('threads_user_id', $threadsUserId)->exists()) {
            Log::warning('Threads uninstall callback matched no connection.', ['threads_user_id' => $threadsUserId]);
        }

        return $matched;
    }
}
