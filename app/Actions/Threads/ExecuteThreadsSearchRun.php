<?php

namespace App\Actions\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Enums\ThreadsSearchRunOutcome;
use App\Events\ThreadsPostsStored;
use App\Models\ThreadsKeyword;
use App\Models\ThreadsPost;
use App\Models\ThreadsSearchRun;
use App\Services\Threads\SearchBudget;
use App\Services\Threads\ThreadsApiException;
use App\Services\Threads\ThreadsClient;
use App\Services\Threads\ThreadsErrorKind;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteThreadsSearchRun
{
    /**
     * Lowest `since` value the Threads keyword search accepts.
     */
    private const MINIMUM_SINCE = 1688540400;

    /**
     * Consecutive search windows overlap by this many minutes.
     */
    private const WINDOW_OVERLAP_MINUTES = 10;

    private const PAUSE_MINUTES = 60;

    /**
     * Hosts whose https permalinks are kept.
     *
     * @var array<int, string>
     */
    private const PERMALINK_HOSTS = ['threads.com', 'www.threads.com', 'threads.net', 'www.threads.net'];

    public function __construct(
        private ThreadsClient $client,
        private SearchBudget $budget,
    ) {}

    /**
     * Run the keyword searches of one search run.
     */
    public function handle(ThreadsSearchRun $run): void
    {
        $isActive = ThreadsSearchRun::query()->active()->whereKey($run->id)->exists();

        if ($run->finished_at !== null || ! $isActive) {
            return;
        }

        $startedAt = now();
        $run->forceFill(['started_at' => $startedAt])->save();

        $user = $run->user;
        $connection = $user->threadsConnection;
        $token = $connection?->status === ThreadsConnectionStatus::Connected ? $connection->usableAccessToken() : null;

        if ($token === null) {
            $this->finish($run, ThreadsSearchRunOutcome::StoppedDisconnected, []);

            return;
        }

        if ($connection->search_paused_until !== null && $connection->search_paused_until->isFuture()) {
            $this->finish($run, ThreadsSearchRunOutcome::PausedQuota, []);

            return;
        }

        $until = $startedAt->getTimestamp();
        $outcome = null;
        $hasFailedKeyword = false;
        $newPostIds = [];

        foreach ($user->threadsKeywords()->orderBy('id')->get() as $keyword) {
            if ($this->budget->queriesUsedLast24Hours($user) >= SearchBudget::DAILY_BUDGET) {
                $outcome = ThreadsSearchRunOutcome::BudgetReached;
                break;
            }

            if (! $connection->isStillSameConnection()) {
                $outcome = ThreadsSearchRunOutcome::StoppedDisconnected;
                break;
            }

            $since = max($keyword->search_window_start->getTimestamp(), self::MINIMUM_SINCE);

            if ($since >= $until) {
                continue;
            }

            $run->increment('queries_sent');

            try {
                $items = $this->client->keywordSearch($token, $keyword->keyword, $since, $until);
            } catch (ThreadsApiException $exception) {
                if ($exception->kind === ThreadsErrorKind::TokenInvalid) {
                    $connection->markDisconnectedIfUnchanged(ThreadsDisconnectReason::TokenInvalid);
                    $outcome = ThreadsSearchRunOutcome::StoppedDisconnected;
                    break;
                }

                if ($exception->kind === ThreadsErrorKind::RateLimited) {
                    $connection->pauseSearchIfConnected(now()->addMinutes(self::PAUSE_MINUTES));
                    Log::warning('Threads search rate limit reached; search paused.', [
                        'user_id' => $user->id,
                        'keyword_id' => $keyword->id,
                        'http_status' => $exception->httpStatus,
                        'threads_code' => $exception->threadsCode,
                        'body' => $exception->sanitizedBody,
                    ]);
                    $outcome = ThreadsSearchRunOutcome::PausedQuota;
                    break;
                }

                $hasFailedKeyword = true;

                if ($exception->kind === ThreadsErrorKind::Transient) {
                    Log::warning('Threads keyword search failed temporarily.', [
                        'keyword_id' => $keyword->id,
                        'http_status' => $exception->httpStatus,
                    ]);
                } else {
                    Log::error('Threads keyword search failed.', [
                        'keyword_id' => $keyword->id,
                        'http_status' => $exception->httpStatus,
                        'threads_code' => $exception->threadsCode,
                    ]);
                }

                continue;
            }

            $storedIds = DB::transaction(function () use ($connection, $run, $keyword, $items, $until): ?array {
                $isConnected = $connection->isStillSameConnection();

                if (! $isConnected) {
                    return null;
                }

                $storedIds = $this->storePosts($run, $keyword, $items);
                $this->recordSuccessfulSearch($keyword, count($items), $until);

                return $storedIds;
            });

            if ($storedIds === null) {
                $outcome = ThreadsSearchRunOutcome::StoppedDisconnected;
                break;
            }

            array_push($newPostIds, ...$storedIds);
        }

        $this->finish($run, $outcome ?? ($hasFailedKeyword ? ThreadsSearchRunOutcome::Partial : ThreadsSearchRunOutcome::Completed), $newPostIds);
    }

    /**
     * Store the posts that are new for the user and return their ids.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, int>
     */
    private function storePosts(ThreadsSearchRun $run, ThreadsKeyword $keyword, array $items): array
    {
        $candidates = [];

        foreach ($items as $item) {
            $candidate = $this->candidateFrom($item);

            if ($candidate === null) {
                Log::warning('Skipped a Threads search result without a usable id or timestamp.', [
                    'keyword_id' => $keyword->id,
                ]);

                continue;
            }

            $candidates[$candidate['threads_post_id']] ??= $candidate;
        }

        if ($candidates === []) {
            return [];
        }

        $existingIds = ThreadsPost::query()
            ->where('user_id', $run->user_id)
            ->whereIn('threads_post_id', array_keys($candidates))
            ->pluck('threads_post_id')
            ->all();

        $storedIds = [];

        foreach ($candidates as $postId => $candidate) {
            if (in_array((string) $postId, array_map('strval', $existingIds), true)) {
                continue;
            }

            try {
                $post = DB::transaction(fn (): ThreadsPost => ThreadsPost::query()->create([
                    ...$candidate,
                    'user_id' => $run->user_id,
                    'threads_keyword_id' => $keyword->id,
                    'keyword' => $keyword->keyword,
                ]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $storedIds[] = $post->id;
        }

        return $storedIds;
    }

    /**
     * Validate one returned item; null when it lacks an id or a parseable timestamp.
     *
     * @return array{threads_post_id: string, text: string|null, permalink: string|null, username: string, posted_at: Carbon}|null
     */
    private function candidateFrom(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $id = $item['id'] ?? null;
        $timestamp = $item['timestamp'] ?? null;

        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $id = (string) $id;

        if ($id === '' || strlen($id) > 64 || ! is_string($timestamp) || $timestamp === '') {
            return null;
        }

        try {
            $postedAt = Carbon::parse($timestamp)->utc();
        } catch (Throwable) {
            return null;
        }

        return [
            'threads_post_id' => $id,
            'text' => isset($item['text']) && is_string($item['text']) && $item['text'] !== '' ? $item['text'] : null,
            'permalink' => $this->safePermalink($item['permalink'] ?? null),
            'username' => is_string($item['username'] ?? null) ? mb_substr($item['username'], 0, 255) : '',
            'posted_at' => $postedAt,
        ];
    }

    private function safePermalink(mixed $permalink): ?string
    {
        if (! is_string($permalink) || strlen($permalink) > 2048) {
            return null;
        }

        $parts = parse_url($permalink);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https') {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');

        return in_array($host, self::PERMALINK_HOSTS, true) ? $permalink : null;
    }

    /**
     * Record a successful query without overwriting a rename that happened during the run.
     */
    private function recordSuccessfulSearch(ThreadsKeyword $keyword, int $resultCount, int $until): void
    {
        $zeroResultsSince = $resultCount > 0
            ? null
            : ($keyword->zero_results_since ?? now());

        ThreadsKeyword::query()
            ->whereKey($keyword->id)
            ->where('keyword', $keyword->keyword)
            ->update([
                'search_window_start' => Carbon::createFromTimestampUTC($until)->subMinutes(self::WINDOW_OVERLAP_MINUTES),
                'last_searched_at' => now(),
                'last_result_count' => $resultCount,
                'zero_results_since' => $zeroResultsSince,
            ]);
    }

    /**
     * Close the run, dispatch the event for new posts and write the summary log entry.
     *
     * @param  array<int, int>  $newPostIds
     */
    private function finish(ThreadsSearchRun $run, ThreadsSearchRunOutcome $outcome, array $newPostIds): void
    {
        $run->forceFill([
            'finished_at' => now(),
            'posts_stored' => count($newPostIds),
            'outcome' => $outcome,
        ])->save();

        if ($newPostIds !== []) {
            ThreadsPostsStored::dispatch($run->user_id, $newPostIds);
        }

        Log::info('Threads search run finished.', [
            'run_id' => $run->id,
            'user_id' => $run->user_id,
            'trigger' => $run->trigger->value,
            'queries_sent' => $run->queries_sent,
            'posts_stored' => count($newPostIds),
            'outcome' => $outcome->value,
        ]);
    }
}
