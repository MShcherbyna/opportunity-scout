<?php

namespace App\Console\Commands;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use App\Services\Threads\ThreadsApiException;
use App\Services\Threads\ThreadsClient;
use App\Services\Threads\ThreadsErrorKind;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('threads:refresh-tokens')]
#[Description('Refresh Threads long-lived tokens that are about to expire')]
class RefreshThreadsTokens extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ThreadsClient $client): int
    {
        $refreshed = 0;
        $failed = 0;
        $disconnected = 0;

        ThreadsConnection::query()
            ->where('status', ThreadsConnectionStatus::Connected)
            ->where('token_expires_at', '<=', now()->addDays(7))
            ->lazyById()
            ->each(function (ThreadsConnection $connection) use ($client, &$refreshed, &$failed, &$disconnected): void {
                if ($connection->token_expires_at !== null && $connection->token_expires_at->lte(now())) {
                    if ($connection->markDisconnectedIfUnchanged(ThreadsDisconnectReason::Expired)) {
                        $disconnected++;
                    }

                    return;
                }

                if ($connection->token_issued_at !== null && $connection->token_issued_at->gt(now()->subDay())) {
                    return;
                }

                $token = $connection->usableAccessToken();

                if ($token === null) {
                    if ($connection->status === ThreadsConnectionStatus::Disconnected) {
                        $disconnected++;
                    }

                    return;
                }

                try {
                    $result = $client->refreshToken($token);
                } catch (ThreadsApiException $exception) {
                    match ($exception->kind) {
                        ThreadsErrorKind::TokenInvalid => $this->disconnectInvalid($connection, $disconnected),
                        ThreadsErrorKind::Transient => $this->logTransient($connection, $failed),
                        ThreadsErrorKind::RateLimited,
                        ThreadsErrorKind::Other => $this->logOther($connection, $exception, $failed),
                    };

                    return;
                }

                if ($connection->storeRefreshedTokenIfUnchanged($result['access_token'], $result['expires_in'])) {
                    $refreshed++;
                }
            });

        Log::info('Threads token refresh finished.', [
            'refreshed' => $refreshed,
            'failed' => $failed,
            'disconnected' => $disconnected,
        ]);

        return self::SUCCESS;
    }

    private function disconnectInvalid(ThreadsConnection $connection, int &$disconnected): void
    {
        if ($connection->markDisconnectedIfUnchanged(ThreadsDisconnectReason::TokenInvalid)) {
            $disconnected++;
        }
    }

    private function logOther(ThreadsConnection $connection, ThreadsApiException $exception, int &$failed): void
    {
        Log::error('Threads token refresh failed.', [
            'connection_id' => $connection->id,
            'threads_code' => $exception->threadsCode,
            'http_status' => $exception->httpStatus,
        ]);
        $failed++;
    }

    private function logTransient(ThreadsConnection $connection, int &$failed): void
    {
        Log::warning('Threads token refresh failed temporarily.', ['connection_id' => $connection->id]);
        $failed++;
    }
}
