<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class ThreadsDisconnectTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureThreads();
    }

    /**
     * SPEC-01 AC-11, AC-12: confirming deletes the token, sets reason `user` and flashes T-6, without calling Threads.
     */
    public function test_disconnect_deletes_the_token_and_sets_reason_user(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $this->fakeThreads();
        $user = User::factory()->create();
        $connection = ThreadsConnection::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('threads.disconnect'));

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.type', 'success');
        $response->assertInertiaFlash('toast.message', 'Threads account disconnected.');
        $stored = $connection->fresh();
        $this->assertSame(ThreadsConnectionStatus::Disconnected, $stored->status);
        $this->assertSame(ThreadsDisconnectReason::User, $stored->disconnect_reason);
        $this->assertSame('2026-10-23 12:00:00', $stored->disconnected_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNull(DB::table('threads_connections')->where('id', $connection->id)->value('access_token'));
        Http::assertNothingSent();
    }

    /**
     * SPEC-01 AC-14: keywords and found posts are kept.
     */
    public function test_disconnect_keeps_keywords_and_posts(): void
    {
        $user = User::factory()->create();
        $connection = ThreadsConnection::factory()->for($user)->create();
        $this->makeKeyword($user, 'laravel');
        $this->makePost($user);

        $this->actingAs($user)->delete(route('threads.disconnect'));

        $this->assertSame(ThreadsConnectionStatus::Disconnected, $connection->fresh()->status);
        $this->assertSame(1, $user->threadsKeywords()->count());
        $this->assertSame(1, $user->threadsPosts()->count());
    }

    /**
     * SPEC-01 RT-4 (T-9): disconnecting without a connection flashes an info toast and changes nothing.
     */
    public function test_disconnect_without_a_connection_flashes_not_connected_and_changes_nothing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('threads.disconnect'));

        $response->assertRedirect(route('settings.threads'));
        $response->assertInertiaFlash('toast.type', 'info');
        $response->assertInertiaFlash('toast.message', 'Threads is not connected.');
        $this->assertDatabaseCount('threads_connections', 0);
    }

    /**
     * SPEC-01 RT-4 (T-9): an already disconnected connection keeps its original reason.
     */
    public function test_disconnect_of_an_already_disconnected_connection_keeps_its_reason(): void
    {
        $user = User::factory()->create();
        $connection = ThreadsConnection::factory()->for($user)->disconnected(ThreadsDisconnectReason::Uninstalled)->create();
        $before = $connection->fresh()->getAttributes();

        $response = $this->actingAs($user)->delete(route('threads.disconnect'));

        $response->assertInertiaFlash('toast.message', 'Threads is not connected.');
        $this->assertSame($before, $connection->fresh()->getAttributes());
    }

    /**
     * SPEC-01 NFR-6: a guest cannot disconnect.
     */
    public function test_guests_cannot_disconnect(): void
    {
        $this->delete(route('threads.disconnect'))->assertRedirect(route('login'));
    }
}
