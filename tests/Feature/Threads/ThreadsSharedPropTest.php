<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThreadsSharedPropTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function signedInPages(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'posts' => ['posts.index'],
            'scraping settings' => ['settings.keywords'],
            'threads settings' => ['settings.threads'],
            'reply template settings' => ['settings.replyTemplate.show'],
            'account profile' => ['profile.edit'],
        ];
    }

    /**
     * SPEC-01 AC-68, MC-10: a disconnected connection is shared on every signed-in page (it drives banner B-1).
     */
    #[DataProvider('signedInPages')]
    public function test_disconnected_connection_is_shared_on_every_signed_in_page(string $routeName): void
    {
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->disconnected(ThreadsDisconnectReason::TokenInvalid)->create(['username' => 'elrondhalfelven']);

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertInertia(fn (Assert $page) => $page
                ->has('threadsConnection', fn (Assert $connection) => $connection
                    ->where('status', 'disconnected')
                    ->where('disconnectReason', 'token_invalid')
                    ->where('username', 'elrondhalfelven')
                )
            );
    }

    /**
     * SPEC-01 MC-10, NFR-2: a connected account is shared without a reason and without the token.
     */
    public function test_connected_connection_is_shared_without_reason_or_token(): void
    {
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->create(['username' => 'elrondhalfelven', 'access_token' => 'SECRET-TOKEN']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('threadsConnection', fn (Assert $connection) => $connection
                ->where('status', 'connected')
                ->where('disconnectReason', null)
                ->where('username', 'elrondhalfelven')
            )
        );
        $this->assertStringNotContainsString('SECRET-TOKEN', json_encode($response->inertiaProps()));
    }

    /**
     * SPEC-01 NFR-2 (fix AR-6): the shared `auth.user` never carries the loaded connection, whatever the page loads.
     */
    #[DataProvider('signedInPages')]
    public function test_auth_user_prop_carries_no_threads_connection(string $routeName): void
    {
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->create(['access_token' => 'SECRET-TOKEN']);

        $response = $this->actingAs($user)->get(route($routeName));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('auth.user')
            ->missing('auth.user.threads_connection')
            ->missing('auth.user.threadsConnection')
        );
        $this->assertStringNotContainsString('SECRET-TOKEN', json_encode($response->inertiaProps()));
    }

    /**
     * SPEC-01 MC-10: a user without a connection gets null.
     */
    public function test_shared_connection_is_null_without_a_connection(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('threadsConnection')->where('threadsConnection', null));
    }
}
