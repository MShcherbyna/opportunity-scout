<?php

namespace App\Actions\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectOutcome;
use App\Enums\ThreadsDisconnectReason;
use App\Models\User;

class DisconnectThreadsAccount
{
    /**
     * Disconnect the user's Threads account at the user's request.
     */
    public function handle(User $user): ThreadsDisconnectOutcome
    {
        $connection = $user->threadsConnection()->first();

        if ($connection === null || $connection->status !== ThreadsConnectionStatus::Connected) {
            return ThreadsDisconnectOutcome::NotConnected;
        }

        $connection->markDisconnected(ThreadsDisconnectReason::User);

        return ThreadsDisconnectOutcome::Disconnected;
    }
}
