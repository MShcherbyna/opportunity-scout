<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThreadsConnectionTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SPEC-01 NFR-1: the token is stored encrypted and readable through the model.
     */
    public function test_access_token_is_encrypted_at_rest(): void
    {
        $connection = ThreadsConnection::factory()->create(['access_token' => 'plain-long-lived-token']);

        $stored = DB::table('threads_connections')->where('id', $connection->id)->value('access_token');

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('plain-long-lived-token', $stored);
        $this->assertSame('plain-long-lived-token', $connection->fresh()->access_token);
    }

    /**
     * SPEC-01 NFR-2: the token never appears when the model is serialized (for example into Inertia props).
     */
    public function test_access_token_is_hidden_from_serialization(): void
    {
        $connection = ThreadsConnection::factory()->create(['access_token' => 'plain-long-lived-token']);

        $this->assertArrayNotHasKey('access_token', $connection->toArray());
        $this->assertStringNotContainsString('plain-long-lived-token', $connection->toJson());
    }

    /**
     * SPEC-01 AC-17: an undecryptable token disconnects the connection with reason token_invalid.
     */
    public function test_undecryptable_token_disconnects_with_reason_token_invalid(): void
    {
        $connection = ThreadsConnection::factory()->create();
        DB::table('threads_connections')->where('id', $connection->id)->update(['access_token' => 'not-an-encrypted-payload']);

        $token = $connection->fresh()->usableAccessToken();

        $this->assertNull($token);

        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Disconnected, $stored->status);
        $this->assertSame(ThreadsDisconnectReason::TokenInvalid, $stored->disconnect_reason);
        $this->assertNull(DB::table('threads_connections')->where('id', $connection->id)->value('access_token'));
    }

    /**
     * SPEC-01 AC-17: a readable token is returned unchanged and the connection stays connected.
     */
    public function test_readable_token_is_returned_and_the_connection_stays_connected(): void
    {
        $connection = ThreadsConnection::factory()->create(['access_token' => 'plain-long-lived-token']);

        $token = $connection->fresh()->usableAccessToken();

        $this->assertSame('plain-long-lived-token', $token);
        $this->assertSame(ThreadsConnectionStatus::Connected, $connection->fresh()->status);
    }

    /**
     * SPEC-01 AC-13 (fix AR-2): an undecryptable token on a stale model does not wipe a connection reconnected meanwhile.
     */
    public function test_undecryptable_token_on_a_stale_model_does_not_wipe_a_reconnected_connection(): void
    {
        $connection = ThreadsConnection::factory()->issuedAt(now()->subDays(3))->create();
        DB::table('threads_connections')->where('id', $connection->id)->update(['access_token' => 'not-an-encrypted-payload']);
        $stale = ThreadsConnection::query()->findOrFail($connection->id);
        DB::table('threads_connections')->where('id', $connection->id)->update([
            'access_token' => Crypt::encryptString('reconnected-token'),
            'token_issued_at' => now(),
        ]);

        $token = $stale->usableAccessToken();

        $this->assertNull($token);
        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Connected, $stored->status);
        $this->assertNull($stored->disconnect_reason);
        $this->assertSame('reconnected-token', $stored->access_token);
    }

    /**
     * SPEC-01 AC-12, AC-13 (fix AR-2): a stale model cannot disconnect a reconnected row, but can disconnect an unchanged one.
     */
    public function test_mark_disconnected_if_unchanged_only_acts_on_the_loaded_token_version(): void
    {
        $reconnected = ThreadsConnection::factory()->issuedAt(now()->subDays(3))->create();
        $unchanged = ThreadsConnection::factory()->issuedAt(now()->subDays(3))->create();
        $stale = ThreadsConnection::query()->findOrFail($reconnected->id);
        $reconnected->update(['access_token' => 'reconnected-token', 'token_issued_at' => now()]);

        $this->assertFalse($stale->markDisconnectedIfUnchanged(ThreadsDisconnectReason::Expired));
        $this->assertTrue(ThreadsConnection::query()->findOrFail($unchanged->id)->markDisconnectedIfUnchanged(ThreadsDisconnectReason::Expired));

        $this->assertSame(ThreadsConnectionStatus::Connected, $reconnected->fresh()->status);
        $this->assertSame('reconnected-token', $reconnected->fresh()->access_token);
        $this->assertSame(ThreadsConnectionStatus::Disconnected, $unchanged->fresh()->status);
        $this->assertSame(ThreadsDisconnectReason::Expired, $unchanged->fresh()->disconnect_reason);
    }

    /**
     * SPEC-01 AC-12, AC-13 (fix AR-14): the row is the same connection while it is connected with the same `connected_at`;
     * a token refresh keeps it, a reconnect or a disconnect ends it.
     */
    public function test_is_still_same_connection_survives_a_token_refresh_but_not_a_reconnect_or_disconnect(): void
    {
        $unchanged = ThreadsConnection::factory()->issuedAt(now()->subDays(3))->create();
        $refreshed = ThreadsConnection::factory()->issuedAt(now()->subDays(3))->create();
        $reconnected = ThreadsConnection::factory()->issuedAt(now()->subDays(3))->create();
        $disconnected = ThreadsConnection::factory()->issuedAt(now()->subDays(3))->create();
        $staleUnchanged = ThreadsConnection::query()->findOrFail($unchanged->id);
        $staleRefreshed = ThreadsConnection::query()->findOrFail($refreshed->id);
        $staleReconnected = ThreadsConnection::query()->findOrFail($reconnected->id);
        $staleDisconnected = ThreadsConnection::query()->findOrFail($disconnected->id);
        $refreshed->update(['access_token' => 'refreshed-token', 'token_issued_at' => now()]);
        $reconnected->update(['access_token' => 'reconnected-token', 'token_issued_at' => now(), 'connected_at' => now()->addMinute()]);
        $disconnected->markDisconnected(ThreadsDisconnectReason::User);

        $this->assertTrue($staleUnchanged->isStillSameConnection());
        $this->assertTrue($staleRefreshed->isStillSameConnection());
        $this->assertFalse($staleReconnected->isStillSameConnection());
        $this->assertFalse($staleDisconnected->isStillSameConnection());
    }
}
