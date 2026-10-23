<?php

namespace Tests\Feature\Threads;

use App\Actions\Threads\DisconnectThreadsAccountsForMeta;
use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class MetaUninstallCallbackTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureThreads();
    }

    /**
     * SPEC-01 AC-18, AC-14: a valid uninstall disconnects the matching connection, keeps its data, leaves other accounts alone.
     */
    public function test_valid_uninstall_disconnects_matching_connections_with_reason_uninstalled(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        $connection = ThreadsConnection::factory()->for($user)->create(['threads_user_id' => '29580006461583503']);
        $other = ThreadsConnection::factory()->create(['threads_user_id' => '777']);
        $this->makeKeyword($user, 'laravel');
        $this->makePost($user);

        $response = $this->post(route('threads.uninstall'), [
            'signed_request' => $this->signedRequest(['user_id' => '29580006461583503']),
        ]);

        $response->assertOk();
        $this->assertSame('', $response->getContent());
        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Disconnected, $stored->status);
        $this->assertSame(ThreadsDisconnectReason::Uninstalled, $stored->disconnect_reason);
        $this->assertSame('2026-10-23 12:00:00', $stored->disconnected_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNull(DB::table('threads_connections')->where('id', $connection->id)->value('access_token'));
        $this->assertSame(ThreadsConnectionStatus::Connected, $other->fresh()->status);
        $this->assertSame(1, $user->threadsKeywords()->count());
        $this->assertSame(1, $user->threadsPosts()->count());
    }

    /**
     * SPEC-01 AC-18, NFR-2: a matched uninstall writes an info log with the Threads user id and the match count, and no token.
     */
    public function test_matched_uninstall_writes_an_info_log_without_secrets(): void
    {
        $logs = $this->captureLogs();
        ThreadsConnection::factory()->create(['threads_user_id' => '29580006461583503', 'access_token' => 'SECRET-TOKEN']);
        $signedRequest = $this->signedRequest(['user_id' => '29580006461583503']);

        $this->post(route('threads.uninstall'), ['signed_request' => $signedRequest])->assertOk();

        $matching = array_values(array_filter(
            $this->logsAt($logs, 'info'),
            fn ($log) => ($log->context['user_id'] ?? null) === '29580006461583503',
        ));
        $this->assertCount(1, $matching);
        $this->assertSame(1, $matching[0]->context['matched_connections']);
        $this->assertNoLogContains($logs, 'SECRET-TOKEN');
        $this->assertNoLogContains($logs, $signedRequest);
    }

    /**
     * SPEC-01 EC-15: Meta resending the callback changes nothing.
     */
    public function test_repeated_uninstall_changes_nothing(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        ThreadsConnection::factory()->create(['threads_user_id' => '29580006461583503']);
        $signedRequest = $this->signedRequest(['user_id' => '29580006461583503']);
        $this->post(route('threads.uninstall'), ['signed_request' => $signedRequest])->assertOk();
        $afterFirst = ThreadsConnection::query()->firstOrFail()->getAttributes();

        $this->travel(1)->hour();
        $this->post(route('threads.uninstall'), ['signed_request' => $signedRequest])->assertOk();

        $this->assertSame($afterFirst, ThreadsConnection::query()->firstOrFail()->getAttributes());
    }

    /**
     * SPEC-01 AC-18, EC-15 (fix AR-8): the action counts only connected rows, so a second call matches none and
     * an already-disconnected row keeps its reason and time.
     */
    public function test_disconnect_action_is_idempotent_and_skips_disconnected_rows(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $connected = ThreadsConnection::factory()->create(['threads_user_id' => '555', 'access_token' => 'SECRET-TOKEN', 'search_paused_until' => now()->addHour()]);
        $alreadyDisconnected = ThreadsConnection::factory()->disconnected(ThreadsDisconnectReason::Expired)->create(['threads_user_id' => '555']);
        $beforeDisconnected = $alreadyDisconnected->fresh()->getAttributes();
        $action = app(DisconnectThreadsAccountsForMeta::class);

        $firstCount = $action->handle('555');
        $this->travel(1)->hour();
        $secondCount = $action->handle('555');

        $this->assertSame(1, $firstCount);
        $this->assertSame(0, $secondCount);
        $stored = $connected->fresh();
        $this->assertSame(ThreadsConnectionStatus::Disconnected, $stored->status);
        $this->assertSame(ThreadsDisconnectReason::Uninstalled, $stored->disconnect_reason);
        $this->assertSame('2026-10-23 12:00:00', $stored->disconnected_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($stored->search_paused_until);
        $this->assertNull(DB::table('threads_connections')->where('id', $connected->id)->value('access_token'));
        $this->assertSame($beforeDisconnected, $alreadyDisconnected->fresh()->getAttributes());
    }

    /**
     * SPEC-01 AC-20 (fix SEC-1): with no app secret configured, a request signed with an empty key is rejected
     * on both Meta callbacks and changes nothing.
     */
    #[DataProvider('metaCallbackRoutes')]
    public function test_signed_request_with_an_empty_secret_is_rejected(string $routeName): void
    {
        config(['services.threads.client_secret' => '']);
        $connection = ThreadsConnection::factory()->create(['threads_user_id' => '29580006461583503']);
        $before = $connection->fresh()->getAttributes();

        $response = $this->post(route($routeName), [
            'signed_request' => $this->signedRequest(['user_id' => '29580006461583503'], ''),
        ]);

        $response->assertBadRequest();
        $this->assertSame($before, $connection->fresh()->getAttributes());
        $this->assertDatabaseCount('threads_data_deletion_requests', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function metaCallbackRoutes(): array
    {
        return [
            'uninstall' => ['threads.uninstall'],
            'data deletion' => ['threads.dataDeletion'],
        ];
    }

    /**
     * SPEC-01 AC-19, D-15: an unmatched id changes nothing, is logged without secrets, and still gets HTTP 200.
     */
    public function test_unmatched_uninstall_logs_the_id_and_returns_200(): void
    {
        $logs = $this->captureLogs();
        $connection = ThreadsConnection::factory()->create(['threads_user_id' => '111', 'access_token' => 'SECRET-TOKEN']);
        $before = $connection->fresh()->getAttributes();
        $signedRequest = $this->signedRequest(['user_id' => '987654321']);

        $this->post(route('threads.uninstall'), ['signed_request' => $signedRequest])->assertOk();

        $this->assertSame($before, $connection->fresh()->getAttributes());
        $mentioning = array_filter($logs->getArrayCopy(), fn ($log) => $this->logMentionsNumber($log, '987654321'));
        $this->assertNotEmpty($mentioning);
        $this->assertNoLogContains($logs, 'SECRET-TOKEN');
        $this->assertNoLogContains($logs, $signedRequest);
        $this->assertNoLogContains($logs, $this->threadsAppSecret);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSignedRequests(): array
    {
        return [
            'missing' => ['missing'],
            'malformed' => ['malformed'],
            'wrong algorithm' => ['wrong_algorithm'],
            'bad signature' => ['bad_signature'],
            'over 8192 characters' => ['too_long'],
        ];
    }

    /**
     * SPEC-01 AC-20, UI-2: an unverifiable `signed_request` gets 400, changes nothing and logs a warning.
     */
    #[DataProvider('invalidSignedRequests')]
    public function test_invalid_signed_requests_return_400_and_change_nothing(string $case): void
    {
        $logs = $this->captureLogs();
        $connection = ThreadsConnection::factory()->create(['threads_user_id' => '29580006461583503']);
        $before = $connection->fresh()->getAttributes();

        $response = $this->post(route('threads.uninstall'), $this->invalidPayload($case));

        $response->assertBadRequest();
        $this->assertSame($before, $connection->fresh()->getAttributes());
        $this->assertNotEmpty($this->logsAt($logs, 'warning'));
    }

    /**
     * SPEC-01 AC-20, NFR-2: the rejection warning contains neither the request body nor the payload.
     */
    public function test_rejected_signed_request_warning_does_not_contain_the_body(): void
    {
        $logs = $this->captureLogs();
        $signedRequest = $this->signedRequest(['user_id' => '29580006461583503', 'marker' => 'LEAK-MARKER'], 'another-secret');
        [$signature, $payload] = explode('.', $signedRequest);

        $this->post(route('threads.uninstall'), ['signed_request' => $signedRequest])->assertBadRequest();

        $this->assertNotEmpty($this->logsAt($logs, 'warning'));
        $this->assertNoLogContains($logs, $signedRequest);
        $this->assertNoLogContains($logs, $signature);
        $this->assertNoLogContains($logs, $payload);
        $this->assertNoLogContains($logs, 'LEAK-MARKER');
        $this->assertNoLogContains($logs, $this->threadsAppSecret);
    }

    /**
     * SPEC-01 RT-5, MC-5: Meta can call the endpoint without a session or cookies; none are created.
     */
    public function test_meta_callbacks_need_no_session_or_csrf_token(): void
    {
        $response = $this->post(route('threads.uninstall'), [
            'signed_request' => $this->signedRequest(['user_id' => '987654321']),
        ]);

        $response->assertOk();
        $response->assertCookieMissing(config('session.cookie'));
        $response->assertCookieMissing('XSRF-TOKEN');
        $this->assertGuest();
    }

    /**
     * SPEC-01 NFR-12: more than 60 Meta callbacks per minute per IP get HTTP 429; another IP is not affected.
     */
    public function test_meta_callbacks_are_throttled_per_ip(): void
    {
        $statuses = [];

        foreach (range(1, 60) as $ignored) {
            $statuses[] = $this->post(route('threads.uninstall'))->getStatusCode();
        }

        $this->assertNotContains(429, $statuses);
        $this->post(route('threads.uninstall'))->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post(route('threads.uninstall'), ['signed_request' => $this->signedRequest(['user_id' => '987654321'])])
            ->assertOk();
    }

    /**
     * SPEC-01 AC-20: no outbound request is made while handling a callback.
     */
    public function test_uninstall_makes_no_threads_request(): void
    {
        $this->fakeThreads();
        ThreadsConnection::factory()->create(['threads_user_id' => '29580006461583503']);

        $this->post(route('threads.uninstall'), ['signed_request' => $this->signedRequest(['user_id' => '29580006461583503'])])->assertOk();

        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function invalidPayload(string $case): array
    {
        $valid = ['user_id' => '29580006461583503'];

        return match ($case) {
            'missing' => [],
            'malformed' => ['signed_request' => 'this-is-not-a-signed-request'],
            'wrong_algorithm' => ['signed_request' => $this->signedRequest([...$valid, 'algorithm' => 'HMAC-SHA1'])],
            'bad_signature' => ['signed_request' => $this->signedRequest($valid, 'another-secret')],
            'too_long' => ['signed_request' => $this->signedRequest([...$valid, 'padding' => str_repeat('a', 9000)])],
        };
    }
}
