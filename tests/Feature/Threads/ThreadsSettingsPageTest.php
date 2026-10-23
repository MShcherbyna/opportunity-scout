<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThreadsSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SPEC-01 AC-10, AC-93, MC-1: the Threads tab receives the connected account details and the expiry date.
     */
    public function test_threads_tab_shows_connected_account_details(): void
    {
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->create([
            'username' => 'elrondhalfelven',
            'name' => 'Elrond',
            'connected_at' => '2026-10-01 10:00:00',
            'token_expires_at' => '2026-11-30 10:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('settings.threads'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('scout-settings/Threads')
                ->where('connection.username', 'elrondhalfelven')
                ->where('connection.name', 'Elrond')
                ->where('connection.status', 'connected')
                ->where('connection.disconnectReason', null)
                ->where('connection.connectedAt', fn (string $value) => Carbon::parse($value)->utc()->format('Y-m-d H:i:s') === '2026-10-01 10:00:00')
                ->where('connection.tokenExpiresAt', fn (string $value) => Carbon::parse($value)->utc()->format('Y-m-d H:i:s') === '2026-11-30 10:00:00')
            );
    }

    /**
     * SPEC-01 AC-15, AC-91: a disconnected connection is shown with its reason (`user` drives notice N-5).
     */
    #[DataProvider('disconnectReasons')]
    public function test_threads_tab_shows_disconnected_state_with_reason(ThreadsDisconnectReason $reason): void
    {
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->disconnected($reason)->create();

        $this->actingAs($user)
            ->get(route('settings.threads'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('scout-settings/Threads')
                ->where('connection.status', 'disconnected')
                ->where('connection.disconnectReason', $reason->value)
            );
    }

    /**
     * @return array<string, array{ThreadsDisconnectReason}>
     */
    public static function disconnectReasons(): array
    {
        return [
            'user' => [ThreadsDisconnectReason::User],
            'uninstalled' => [ThreadsDisconnectReason::Uninstalled],
            'token invalid' => [ThreadsDisconnectReason::TokenInvalid],
            'expired' => [ThreadsDisconnectReason::Expired],
        ];
    }

    /**
     * SPEC-01 AC-1, RT-1: a user who never connected gets a null connection.
     */
    public function test_threads_tab_has_null_connection_when_never_connected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.threads'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('scout-settings/Threads')
                ->where('connection', null)
            );
    }

    /**
     * SPEC-01 NFR-2, NFR-6: no prop contains the access token, and another user's connection is not exposed.
     */
    public function test_threads_tab_props_never_contain_the_access_token(): void
    {
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->create(['username' => 'mine', 'access_token' => 'SECRET-OWN-TOKEN']);
        ThreadsConnection::factory()->create(['username' => 'theirs', 'access_token' => 'SECRET-OTHER-TOKEN']);

        $response = $this->actingAs($user)->get(route('settings.threads'));

        $response->assertInertia(fn (Assert $page) => $page->where('connection.username', 'mine'));
        $props = json_encode($response->inertiaProps());
        $this->assertStringNotContainsString('SECRET-OWN-TOKEN', $props);
        $this->assertStringNotContainsString('SECRET-OTHER-TOKEN', $props);
        $this->assertStringNotContainsString('theirs', $props);
    }
}
