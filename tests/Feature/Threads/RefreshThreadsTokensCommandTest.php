<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class RefreshThreadsTokensCommandTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureThreads();
        $this->travelTo('2026-10-23 12:00:00');
    }

    /**
     * SPEC-01 AC-27, AC-28: a connected token expiring within 7 days and issued a day ago is refreshed with TA-5.
     */
    public function test_refreshes_connections_expiring_within_seven_days_and_issued_a_day_ago(): void
    {
        $this->fakeRefresh();
        $connection = ThreadsConnection::factory()
            ->expiresAt(now()->addDays(6))
            ->issuedAt(now()->subDays(2))
            ->create(['access_token' => 'old-token']);

        Artisan::call('threads:refresh-tokens');

        $this->assertCount(1, Http::recorded());
        [$request] = Http::recorded()[0];
        $this->assertStringStartsWith($this->graphUrl('refresh_access_token'), $request->url());
        $this->assertSame('th_refresh_token', $this->requestParameters($request)['grant_type']);
        $this->assertSame('old-token', $this->requestParameters($request)['access_token']);
        $this->assertSame('new-token', $connection->fresh()->access_token);
    }

    /**
     * SPEC-01 AC-27, AC-12: far-off expiry, a fresh token and disconnected rows are not refreshed.
     */
    public function test_skips_connections_not_expiring_soon_issued_recently_or_disconnected(): void
    {
        $this->fakeRefresh();
        $notExpiringSoon = ThreadsConnection::factory()->expiresAt(now()->addDays(8))->issuedAt(now()->subDays(2))->create();
        $issuedRecently = ThreadsConnection::factory()->expiresAt(now()->addDays(3))->issuedAt(now()->subHours(23))->create();
        $disconnected = ThreadsConnection::factory()->disconnected()->expiresAt(now()->addDays(3))->issuedAt(now()->subDays(2))->create();
        $due = ThreadsConnection::factory()->expiresAt(now()->addDays(3))->issuedAt(now()->subDays(2))->create(['access_token' => 'due-token']);
        $before = [$notExpiringSoon, $issuedRecently, $disconnected];
        $snapshots = array_map(fn (ThreadsConnection $connection) => $connection->fresh()->getAttributes(), $before);

        Artisan::call('threads:refresh-tokens');

        $this->assertCount(1, Http::recorded());
        [$request] = Http::recorded()[0];
        $this->assertSame('due-token', $this->requestParameters($request)['access_token']);
        $this->assertSame('new-token', $due->fresh()->access_token);
        foreach ($before as $index => $connection) {
            $this->assertSame($snapshots[$index], $connection->fresh()->getAttributes());
        }
    }

    /**
     * SPEC-01 AC-9, AC-28: the new expiry is now plus `expires_in` and the issue time is now.
     */
    public function test_saves_the_new_token_expiry_and_issue_time(): void
    {
        $this->fakeRefresh(expiresIn: 3600);
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create();

        Artisan::call('threads:refresh-tokens');

        $stored = $connection->fresh();
        $this->assertSame('new-token', $stored->access_token);
        $this->assertSame('2026-10-23 13:00:00', $stored->token_expires_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 12:00:00', $stored->token_issued_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(ThreadsConnectionStatus::Connected, $stored->status);
    }

    /**
     * SPEC-01 AC-29: one failing connection does not stop the rest.
     */
    public function test_continues_after_one_connection_fails(): void
    {
        $this->fakeThreads([
            $this->graphUrl('refresh_access_token').'*' => function (Request $request) {
                return $this->requestParameters($request)['access_token'] === 'token-failing'
                    ? Http::response([], 500)
                    : Http::response(['access_token' => 'new-token', 'token_type' => 'bearer', 'expires_in' => 5_184_000]);
            },
        ]);
        $failing = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create(['access_token' => 'token-failing']);
        $first = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create(['access_token' => 'token-first']);
        $second = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create(['access_token' => 'token-second']);

        Artisan::call('threads:refresh-tokens');

        $this->assertSame('token-failing', $failing->fresh()->access_token);
        $this->assertSame('new-token', $first->fresh()->access_token);
        $this->assertSame('new-token', $second->fresh()->access_token);
    }

    /**
     * SPEC-01 AC-16, AC-30: error 190 deletes the token and sets reason token_invalid.
     */
    public function test_error_190_disconnects_with_reason_token_invalid(): void
    {
        $this->fakeThreads([$this->graphUrl('refresh_access_token').'*' => $this->threadsError(190, 'Invalid OAuth access token')]);
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create();

        Artisan::call('threads:refresh-tokens');

        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Disconnected, $stored->status);
        $this->assertSame(ThreadsDisconnectReason::TokenInvalid, $stored->disconnect_reason);
        $this->assertNull(DB::table('threads_connections')->where('id', $connection->id)->value('access_token'));
    }

    /**
     * SPEC-01 AC-31: network errors, timeouts and 5xx log a warning with the connection id and change nothing.
     */
    #[DataProvider('transientFailures')]
    public function test_transient_failure_logs_a_warning_and_leaves_the_connection_unchanged(mixed $failure): void
    {
        $this->fakeThreads([
            $this->graphUrl('refresh_access_token').'*' => is_int($failure) ? Http::response([], $failure) : $failure,
        ]);
        $logs = $this->captureLogs();
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create(['access_token' => 'SECRET-OLD-TOKEN']);
        $before = $connection->fresh()->getAttributes();

        Artisan::call('threads:refresh-tokens');

        $this->assertSame($before, $connection->fresh()->getAttributes());
        $warnings = array_filter($this->logsAt($logs, 'warning'), fn ($log) => $this->logMentionsNumber($log, $connection->id));
        $this->assertNotEmpty($warnings);
        $this->assertNoLogContains($logs, 'SECRET-OLD-TOKEN');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function transientFailures(): array
    {
        return [
            'http 500' => [500],
            'http 503' => [503],
            'connection failure' => [HttpFactory::failedConnection('cURL error 28: Operation timed out')],
        ];
    }

    /**
     * SPEC-01 AC-32: any other error logs an error with the connection id and the Threads code and changes nothing.
     */
    public function test_other_error_logs_an_error_with_the_threads_code(): void
    {
        $this->fakeThreads([$this->graphUrl('refresh_access_token').'*' => $this->threadsError(100, 'Invalid parameter')]);
        $logs = $this->captureLogs();
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create();
        $before = $connection->fresh()->getAttributes();

        Artisan::call('threads:refresh-tokens');

        $this->assertSame($before, $connection->fresh()->getAttributes());
        $errors = array_filter(
            $this->logsAt($logs, 'error'),
            fn ($log) => $this->logMentionsNumber($log, $connection->id) && $this->logMentionsNumber($log, 100),
        );
        $this->assertNotEmpty($errors);
    }

    /**
     * SPEC-01 AC-32: a rate-limit signal (Threads code 4 or HTTP 429) logs an error with the connection id and
     * `threads_code`; the connection is not disconnected and keeps its token.
     */
    #[DataProvider('rateLimitSignals')]
    public function test_rate_limit_signal_logs_an_error_and_leaves_the_connection_unchanged(int $httpStatus, ?int $threadsCode): void
    {
        $this->fakeThreads([
            $this->graphUrl('refresh_access_token').'*' => $threadsCode === null
                ? Http::response([], $httpStatus)
                : $this->threadsError($threadsCode, 'Application request limit reached', $httpStatus),
        ]);
        $logs = $this->captureLogs();
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create(['access_token' => 'SECRET-OLD-TOKEN']);
        $before = $connection->fresh()->getAttributes();

        Artisan::call('threads:refresh-tokens');

        $stored = $connection->fresh();
        $this->assertSame($before, $stored->getAttributes());
        $this->assertSame(ThreadsConnectionStatus::Connected, $stored->status);
        $this->assertSame('SECRET-OLD-TOKEN', $stored->access_token);
        $errors = array_values(array_filter(
            $this->logsAt($logs, 'error'),
            fn ($log) => ($log->context['connection_id'] ?? null) === $connection->id,
        ));
        $this->assertCount(1, $errors);
        $this->assertArrayHasKey('threads_code', $errors[0]->context);
        $this->assertSame($threadsCode, $errors[0]->context['threads_code']);
        $this->assertNoLogContains($logs, 'SECRET-OLD-TOKEN');
    }

    /**
     * @return array<string, array{int, int|null}>
     */
    public static function rateLimitSignals(): array
    {
        return [
            'threads code 4' => [400, 4],
            'http 429' => [429, null],
        ];
    }

    /**
     * SPEC-01 AC-33: an expired token is deleted without calling Threads; reason `expired`.
     */
    public function test_expired_token_disconnects_with_reason_expired(): void
    {
        $this->fakeRefresh();
        $connection = ThreadsConnection::factory()->expiresAt(now()->subDay())->issuedAt(now()->subDays(61))->create();

        Artisan::call('threads:refresh-tokens');

        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Disconnected, $stored->status);
        $this->assertSame(ThreadsDisconnectReason::Expired, $stored->disconnect_reason);
        $this->assertNull(DB::table('threads_connections')->where('id', $connection->id)->value('access_token'));
        Http::assertNothingSent();
    }

    /**
     * SPEC-01 AC-17: an undecryptable token is treated like error 190, without a request.
     */
    public function test_undecryptable_token_disconnects_with_reason_token_invalid(): void
    {
        $this->fakeRefresh();
        $logs = $this->captureLogs();
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create();
        DB::table('threads_connections')->where('id', $connection->id)->update(['access_token' => 'not-an-encrypted-payload']);

        Artisan::call('threads:refresh-tokens');

        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Disconnected, $stored->status);
        $this->assertSame(ThreadsDisconnectReason::TokenInvalid, $stored->disconnect_reason);
        Http::assertNothingSent();
        $summaries = $this->logsAt($logs, 'info');
        $this->assertCount(1, $summaries);
        $this->assertSame(1, $summaries[0]->context['disconnected']);
    }

    /**
     * SPEC-01 NFR-8: one summary entry reports the refreshed, failed and disconnected counts.
     */
    public function test_writes_one_summary_log_entry(): void
    {
        $this->fakeThreads([
            $this->graphUrl('refresh_access_token').'*' => function (Request $request) {
                return match ($this->requestParameters($request)['access_token']) {
                    'token-failing' => Http::response([], 500),
                    'token-revoked' => $this->threadsError(190, 'Invalid OAuth access token'),
                    default => Http::response(['access_token' => 'new-token', 'expires_in' => 5_184_000]),
                };
            },
        ]);
        $logs = $this->captureLogs();
        foreach (['token-ok-1', 'token-ok-2', 'token-failing', 'token-revoked'] as $token) {
            ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create(['access_token' => $token]);
        }

        Artisan::call('threads:refresh-tokens');

        $summaries = $this->logsAt($logs, 'info');
        $this->assertCount(1, $summaries);
        $values = $this->logValues($summaries[0]);
        $this->assertContains('2', $values);
        $this->assertGreaterThanOrEqual(2, count(array_keys($values, '1', true)));
    }

    /**
     * SPEC-01 AC-12, AC-28 (fix AR-2): a connection disconnected during the refresh call stays disconnected, without a token.
     */
    public function test_connection_disconnected_during_the_refresh_call_is_not_overwritten(): void
    {
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create(['access_token' => 'old-token']);
        $this->fakeThreads([
            $this->graphUrl('refresh_access_token').'*' => function () use ($connection) {
                ThreadsConnection::query()->findOrFail($connection->id)->markDisconnected(ThreadsDisconnectReason::User);

                return Http::response(['access_token' => 'new-token', 'expires_in' => 5_184_000]);
            },
        ]);

        Artisan::call('threads:refresh-tokens');

        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Disconnected, $stored->status);
        $this->assertSame(ThreadsDisconnectReason::User, $stored->disconnect_reason);
        $this->assertNull(DB::table('threads_connections')->where('id', $connection->id)->value('access_token'));
    }

    /**
     * SPEC-01 AC-13, AC-28 (fix AR-2): a connection reconnected (new `token_issued_at`) during the refresh call
     * keeps the reconnected token.
     */
    public function test_connection_reconnected_during_the_refresh_call_keeps_the_new_token(): void
    {
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create(['access_token' => 'old-token']);
        $this->fakeThreads([
            $this->graphUrl('refresh_access_token').'*' => function () use ($connection) {
                ThreadsConnection::query()->findOrFail($connection->id)->update([
                    'access_token' => 'reconnected-token',
                    'token_issued_at' => now()->subMinute(),
                    'token_expires_at' => now()->addDays(60),
                ]);

                return Http::response(['access_token' => 'new-token', 'expires_in' => 5_184_000]);
            },
        ]);

        Artisan::call('threads:refresh-tokens');

        $stored = $connection->fresh();
        $this->assertSame('reconnected-token', $stored->access_token);
        $this->assertSame('2026-10-23 11:59:00', $stored->token_issued_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-22 12:00:00', $stored->token_expires_at->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-16, AC-30 (fix AR-2): error 190 for a stale token does not disconnect a reconnected connection.
     */
    public function test_error_190_for_a_stale_token_does_not_wipe_a_reconnected_connection(): void
    {
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create(['access_token' => 'old-token']);
        $this->fakeThreads([
            $this->graphUrl('refresh_access_token').'*' => function () use ($connection) {
                ThreadsConnection::query()->findOrFail($connection->id)->update([
                    'access_token' => 'reconnected-token',
                    'token_issued_at' => now()->subMinute(),
                ]);

                return $this->threadsError(190, 'Invalid OAuth access token');
            },
        ]);

        Artisan::call('threads:refresh-tokens');

        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Connected, $stored->status);
        $this->assertSame('reconnected-token', $stored->access_token);
    }

    /**
     * SPEC-01 AC-13, AC-33 (fix AR-2): a reconnect between loading an expired row and the Expired branch keeps the new token.
     */
    public function test_reconnect_before_the_expired_branch_keeps_the_new_token(): void
    {
        $this->fakeRefresh();
        $connection = ThreadsConnection::factory()->expiresAt(now()->subDay())->issuedAt(now()->subDays(61))->create(['access_token' => 'old-token']);
        $this->reconnectRightAfterLoad($connection);

        Artisan::call('threads:refresh-tokens');

        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Connected, $stored->status);
        $this->assertNull($stored->disconnect_reason);
        $this->assertSame('reconnected-token', $stored->access_token);
        Http::assertNothingSent();
    }

    /**
     * SPEC-01 AC-13, AC-17 (fix AR-2): a reconnect between loading a row with an undecryptable token and the decrypt
     * branch does not wipe the reconnected row.
     */
    public function test_reconnect_before_the_decrypt_branch_keeps_the_new_token(): void
    {
        $this->fakeRefresh();
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create();
        DB::table('threads_connections')->where('id', $connection->id)->update(['access_token' => 'not-an-encrypted-payload']);
        $this->reconnectRightAfterLoad($connection);

        Artisan::call('threads:refresh-tokens');

        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Connected, $stored->status);
        $this->assertNull($stored->disconnect_reason);
        $this->assertSame('reconnected-token', $stored->access_token);
        Http::assertNothingSent();
    }

    /**
     * SPEC-01 NFR-8 (fix AR-15): when the decrypt branch finds the row reconnected, nothing was disconnected,
     * so the summary reports disconnected = 0.
     */
    public function test_summary_does_not_count_a_disconnect_that_did_not_happen(): void
    {
        $this->fakeRefresh();
        $logs = $this->captureLogs();
        $connection = ThreadsConnection::factory()->expiresAt(now()->addDays(2))->issuedAt(now()->subDays(3))->create();
        DB::table('threads_connections')->where('id', $connection->id)->update(['access_token' => 'not-an-encrypted-payload']);
        $this->reconnectRightAfterLoad($connection);

        Artisan::call('threads:refresh-tokens');

        $summaries = $this->logsAt($logs, 'info');
        $this->assertCount(1, $summaries);
        $this->assertSame(0, $summaries[0]->context['disconnected']);
        $this->assertSame(ThreadsConnectionStatus::Connected, $connection->fresh()->status);
    }

    /**
     * Simulate a reconnect that lands after the command hydrated the connection but before it acts on it.
     */
    private function reconnectRightAfterLoad(ThreadsConnection $connection): void
    {
        ThreadsConnection::retrieved(function (ThreadsConnection $loaded) use ($connection): void {
            if ($loaded->id !== $connection->id || $loaded->token_issued_at?->gt(now()->subMinutes(5))) {
                return;
            }

            DB::table('threads_connections')->where('id', $connection->id)->update([
                'access_token' => Crypt::encryptString('reconnected-token'),
                'token_issued_at' => now(),
                'token_expires_at' => now()->addDays(60),
            ]);
        });
    }

    private function fakeRefresh(int $expiresIn = 5_184_000): void
    {
        $this->fakeThreads([
            $this->graphUrl('refresh_access_token').'*' => Http::response([
                'access_token' => 'new-token',
                'token_type' => 'bearer',
                'expires_in' => $expiresIn,
            ]),
        ]);
    }
}
