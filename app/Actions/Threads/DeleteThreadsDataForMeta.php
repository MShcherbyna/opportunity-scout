<?php

namespace App\Actions\Threads;

use App\Enums\ThreadsDataDeletionStatus;
use App\Models\ThreadsConnection;
use App\Models\ThreadsDataDeletionRequest;
use App\Models\ThreadsPost;
use App\Models\ThreadsSearchRun;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeleteThreadsDataForMeta
{
    /**
     * Delete the data of every connection with the given Threads user id.
     *
     * Posts and search runs of the connections' users and the connections themselves are removed;
     * keywords and accounts stay. A completed request is recorded without the Threads user id.
     */
    public function handle(string $threadsUserId): ThreadsDataDeletionRequest
    {
        $matched = 0;

        $request = DB::transaction(function () use ($threadsUserId, &$matched): ThreadsDataDeletionRequest {
            $connections = ThreadsConnection::query()
                ->where('threads_user_id', $threadsUserId)
                ->lockForUpdate()
                ->get(['id', 'user_id']);

            $matched = $connections->count();

            if ($matched > 0) {
                $userIds = $connections->pluck('user_id')->unique()->all();

                ThreadsPost::query()->whereIn('user_id', $userIds)->delete();
                ThreadsSearchRun::query()->whereIn('user_id', $userIds)->delete();
                ThreadsConnection::query()->whereIn('id', $connections->pluck('id')->all())->delete();
            }

            return $this->recordCompletedRequest();
        });

        if ($matched === 0) {
            Log::warning('Threads data deletion callback matched no connection.', ['threads_user_id' => $threadsUserId]);
        } else {
            Log::info('Threads data deleted for a Meta callback.', ['user_id' => $threadsUserId, 'matched_connections' => $matched]);
        }

        return $request;
    }

    private function recordCompletedRequest(): ThreadsDataDeletionRequest
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(fn (): ThreadsDataDeletionRequest => ThreadsDataDeletionRequest::query()->create([
                    'confirmation_code' => Str::random(16),
                    'status' => ThreadsDataDeletionStatus::Completed,
                    'requested_at' => now(),
                    'completed_at' => now(),
                ]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        return ThreadsDataDeletionRequest::query()->create([
            'confirmation_code' => Str::random(24),
            'status' => ThreadsDataDeletionStatus::Completed,
            'requested_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
