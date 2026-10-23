<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class ThreadsCallbackTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    private const STATE = 'valid-state-value-0123456789';

    private const THREADS_ID = '29580006461583503';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureThreads();
    }

    /**
     * SPEC-01 AC-5, AC-10: a valid callback saves a connected connection and flashes T-1.
     */
    public function test_valid_callback_saves_a_connected_connection_and_flashes_success(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $this->fakeSuccessfulExchange();
        $user = User::factory()->create();

        $response = $this->visitCallback($user);

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.type', 'success');
        $response->assertInertiaFlash('toast.message', 'Threads account connected.');

        $connection = $user->threadsConnection()->firstOrFail();
        $this->assertSame(ThreadsConnectionStatus::Connected, $connection->status);
        $this->assertSame(self::THREADS_ID, $connection->threads_user_id);
        $this->assertSame('elrondhalfelven', $connection->username);
        $this->assertSame('Elrond', $connection->name);
        $this->assertSame('long-lived-token', $connection->access_token);
        $this->assertNull($connection->disconnect_reason);
        $this->assertSame('2026-10-23 12:00:00', $connection->connected_at->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-9: expiry is the exchange time plus `expires_in`; the issue time is the exchange time.
     */
    public function test_token_expiry_and_issue_time_come_from_the_exchange_time(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $this->fakeSuccessfulExchange(expiresIn: 3600);
        $user = User::factory()->create();

        $this->visitCallback($user);

        $this->assertDatabaseCount('threads_connections', 1);
        $connection = $user->threadsConnection()->firstOrFail();
        $this->assertSame('2026-10-23 12:00:00', $connection->token_issued_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 13:00:00', $connection->token_expires_at->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-3: a missing state makes no API call and flashes T-3.
     */
    public function test_missing_state_makes_no_api_call_and_flashes_verification_error(): void
    {
        $this->fakeThreads();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['threads_oauth_state' => self::STATE])
            ->get(route('threads.callback', ['code' => 'auth-code']));

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.type', 'error');
        $response->assertInertiaFlash('toast.message', "We couldn't verify the Threads connection. Please try again.");
        Http::assertNothingSent();
        $this->assertDatabaseCount('threads_connections', 0);
    }

    /**
     * SPEC-01 AC-3: a state that differs from the session value makes no API call and flashes T-3.
     */
    public function test_mismatched_state_makes_no_api_call_and_flashes_verification_error(): void
    {
        $this->fakeThreads();
        $user = User::factory()->create();

        $response = $this->visitCallback($user, ['state' => 'another-state-value-0123456789']);

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.message', "We couldn't verify the Threads connection. Please try again.");
        Http::assertNothingSent();
        $this->assertDatabaseCount('threads_connections', 0);
    }

    /**
     * SPEC-01 AC-2, AC-3, EC-2: the state works for one callback only.
     */
    public function test_state_is_accepted_for_one_callback_only(): void
    {
        $this->fakeSuccessfulExchange();
        $user = User::factory()->create();
        $this->visitCallback($user);
        $user->threadsConnection()->delete();

        $response = $this->actingAs($user)->get(route('threads.callback', ['state' => self::STATE, 'code' => 'auth-code']));

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.message', "We couldn't verify the Threads connection. Please try again.");
        $this->assertDatabaseCount('threads_connections', 0);
    }

    /**
     * SPEC-01 AC-4, UI-1: a declined authorization changes nothing and never shows the Threads text.
     */
    public function test_error_parameter_leaves_the_connection_unchanged_and_never_echoes_it(): void
    {
        $this->fakeThreads();
        $user = User::factory()->create();
        $connection = ThreadsConnection::factory()->for($user)->disconnected(ThreadsDisconnectReason::Uninstalled)->create();
        $before = $connection->fresh()->getAttributes();

        $response = $this->visitCallback($user, [
            'error' => 'access_denied',
            'error_reason' => 'user_denied',
            'error_description' => '<script>alert(1)</script> Call 555-0100',
        ]);

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.type', 'error');
        $response->assertInertiaFlash('toast.message', 'Threads access was not granted.');
        $location = (string) $response->headers->get('Location');
        $content = (string) $response->getContent();
        $flash = json_encode(session()->get('_flash', []));
        $stored = Arr::except(session()->all(), ['_previous']);

        foreach (['script', '555-0100', 'access_denied', 'user_denied'] as $errorValue) {
            $this->assertStringNotContainsString($errorValue, $location);
            $this->assertStringNotContainsString($errorValue, $content);
            $this->assertStringNotContainsString($errorValue, $flash);
            $this->assertStringNotContainsString($errorValue, json_encode($stored));
        }
        $this->assertSame($before, $connection->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    /**
     * SPEC-01 AC-6: a failed code exchange changes nothing and flashes T-4.
     */
    public function test_failed_code_exchange_flashes_connection_failed(): void
    {
        $this->fakeThreads([
            $this->graphUrl('oauth/access_token').'*' => Http::response([
                'error_type' => 'OAuthException',
                'code' => 400,
                'error_message' => 'Matching code was not found or was already used',
            ], 400),
        ]);
        $user = User::factory()->create();
        $connection = ThreadsConnection::factory()->for($user)->disconnected()->create();
        $before = $connection->fresh()->getAttributes();

        $response = $this->visitCallback($user);

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.type', 'error');
        $response->assertInertiaFlash('toast.message', 'Connecting Threads failed. Please try again.');
        $this->assertSame($before, $connection->fresh()->getAttributes());
    }

    /**
     * SPEC-01 AC-6, plan section 3: a callback without `code` and without `error` counts as a failed exchange.
     */
    public function test_missing_code_flashes_connection_failed(): void
    {
        $this->fakeThreads();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['threads_oauth_state' => self::STATE])
            ->get(route('threads.callback', ['state' => self::STATE]));

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.message', 'Connecting Threads failed. Please try again.');
        Http::assertNothingSent();
        $this->assertDatabaseCount('threads_connections', 0);
    }

    /**
     * SPEC-01 AC-7: a failed long-lived exchange discards the short token and flashes T-4.
     */
    public function test_failed_long_lived_exchange_flashes_connection_failed(): void
    {
        $this->fakeThreads([
            $this->graphUrl('oauth/access_token').'*' => Http::response(['access_token' => 'short-lived-token', 'user_id' => self::THREADS_ID]),
            $this->graphUrl('access_token').'*' => $this->threadsError(100, 'Invalid parameter'),
        ]);
        $user = User::factory()->create();

        $response = $this->visitCallback($user);

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.message', 'Connecting Threads failed. Please try again.');
        $this->assertDatabaseCount('threads_connections', 0);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'v1.0/me'));
    }

    /**
     * SPEC-01 AC-7: a failed profile call discards the tokens and flashes T-4.
     */
    public function test_failed_profile_call_flashes_connection_failed(): void
    {
        $this->fakeThreads([
            $this->graphUrl('oauth/access_token').'*' => Http::response(['access_token' => 'short-lived-token', 'user_id' => self::THREADS_ID]),
            $this->graphUrl('access_token').'*' => Http::response(['access_token' => 'long-lived-token', 'expires_in' => 5_184_000]),
            $this->graphUrl('v1.0/me').'*' => Http::response([], 500),
        ]);
        $user = User::factory()->create();

        $response = $this->visitCallback($user);

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.message', 'Connecting Threads failed. Please try again.');
        $this->assertDatabaseCount('threads_connections', 0);
    }

    /**
     * SPEC-01 AC-67, D-10: an account that another user has `connected` is rejected with T-5.
     */
    public function test_account_connected_to_another_user_is_rejected(): void
    {
        $this->fakeSuccessfulExchange();
        $owner = User::factory()->create();
        $ownerConnection = ThreadsConnection::factory()->for($owner)->create(['threads_user_id' => self::THREADS_ID, 'access_token' => 'owners-token']);
        $ownerBefore = $ownerConnection->fresh()->getAttributes();
        $user = User::factory()->create();

        $response = $this->visitCallback($user);

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.type', 'error');
        $response->assertInertiaFlash('toast.message', 'This Threads account is already connected to another Opportunity Scout account.');
        $this->assertSame($ownerBefore, $ownerConnection->fresh()->getAttributes());
        $this->assertDatabaseMissing('threads_connections', ['user_id' => $user->id]);
        $this->assertDatabaseCount('threads_connections', 1);
    }

    /**
     * SPEC-01 AC-67, AC-13: an account that another user only has `disconnected` can be connected.
     */
    public function test_account_disconnected_from_another_user_can_be_connected(): void
    {
        $this->fakeSuccessfulExchange();
        $previousOwner = User::factory()->create();
        $previousConnection = ThreadsConnection::factory()->for($previousOwner)->disconnected()->create(['threads_user_id' => self::THREADS_ID]);
        $previousBefore = $previousConnection->fresh()->getAttributes();
        $user = User::factory()->create();

        $response = $this->visitCallback($user);

        $response->assertInertiaFlash('toast.message', 'Threads account connected.');
        $this->assertSame(ThreadsConnectionStatus::Connected, $user->threadsConnection()->firstOrFail()->status);
        $this->assertSame($previousBefore, $previousConnection->fresh()->getAttributes());
    }

    /**
     * SPEC-01 AC-13, D-11: reconnecting updates the same row, even with another account, and keeps keywords and posts.
     */
    public function test_reconnecting_with_a_different_account_updates_the_same_row_and_keeps_keywords_and_posts(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $this->fakeSuccessfulExchange();
        $user = User::factory()->create();
        $connection = ThreadsConnection::factory()->for($user)->disconnected(ThreadsDisconnectReason::Expired)->create([
            'connected_at' => '2026-10-01 08:00:00',
            'threads_user_id' => '111111',
            'username' => 'old_account',
            'name' => 'Old Name',
        ]);
        $this->makeKeyword($user, 'laravel');
        $this->makePost($user);

        $this->visitCallback($user);

        $this->assertDatabaseCount('threads_connections', 1);
        $updated = $connection->fresh();
        $this->assertSame(self::THREADS_ID, $updated->threads_user_id);
        $this->assertSame('elrondhalfelven', $updated->username);
        $this->assertSame('Elrond', $updated->name);
        $this->assertSame('long-lived-token', $updated->access_token);
        $this->assertSame(ThreadsConnectionStatus::Connected, $updated->status);
        $this->assertNull($updated->disconnect_reason);
        $this->assertSame(1, $user->threadsKeywords()->count());
        $this->assertSame(1, $user->threadsPosts()->count());
        $this->assertSame('2026-10-23 12:00:00', $updated->connected_at->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-13 (fix AR-16): reconnecting the same Threads account writes a new `connected_at`, so in-flight runs stop.
     */
    public function test_reconnecting_the_same_account_writes_a_new_connected_at(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $this->fakeSuccessfulExchange();
        $user = User::factory()->create();
        $connection = ThreadsConnection::factory()->for($user)->create([
            'connected_at' => '2026-10-01 08:00:00',
            'threads_user_id' => self::THREADS_ID,
        ]);
        $loaded = $connection->fresh();

        $this->visitCallback($user);

        $this->assertDatabaseCount('threads_connections', 1);
        $this->assertSame('2026-10-23 12:00:00', $connection->fresh()->connected_at->utc()->format('Y-m-d H:i:s'));
        $this->assertFalse($loaded->isStillSameConnection());
    }

    /**
     * SPEC-01 AC-8, NFR-2: the short-lived token is never stored, put in the session, queued or logged.
     */
    public function test_short_lived_token_is_never_stored_logged_or_put_in_the_session(): void
    {
        $this->fakeSuccessfulExchange();
        Queue::fake();
        $logs = $this->captureLogs();
        $user = User::factory()->create();

        $this->visitCallback($user);

        $this->assertDatabaseCount('threads_connections', 1);
        $this->assertSame('long-lived-token', $user->threadsConnection()->firstOrFail()->access_token);
        $this->assertStringNotContainsString('short-lived-token', json_encode(DB::table('threads_connections')->get()));
        $this->assertStringNotContainsString('short-lived-token', json_encode(session()->all()));
        $this->assertNoLogContains($logs, 'short-lived-token');
        $this->assertNoLogContains($logs, 'auth-code');
        Queue::assertNothingPushed();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function visitCallback(User $user, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)
            ->withSession(['threads_oauth_state' => self::STATE])
            ->get(route('threads.callback', ['state' => self::STATE, 'code' => 'auth-code', ...$overrides]));
    }

    private function fakeSuccessfulExchange(int $expiresIn = 5_184_000): void
    {
        $this->fakeThreads([
            $this->graphUrl('oauth/access_token').'*' => Http::response(['access_token' => 'short-lived-token', 'user_id' => self::THREADS_ID]),
            $this->graphUrl('access_token').'*' => Http::response(['access_token' => 'long-lived-token', 'token_type' => 'bearer', 'expires_in' => $expiresIn]),
            $this->graphUrl('v1.0/me').'*' => Http::response(['id' => self::THREADS_ID, 'username' => 'elrondhalfelven', 'name' => 'Elrond']),
        ]);
    }
}
