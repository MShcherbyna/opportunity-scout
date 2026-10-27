<?php

namespace Tests\Feature\Threads;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class ThreadsConnectTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureThreads();
    }

    /**
     * SPEC-01 AC-1: "Connect Threads" redirects to the authorization URL with the required parameters.
     */
    public function test_connect_redirects_to_the_threads_authorization_url_with_required_parameters(): void
    {
        $this->fakeThreads();

        $response = $this->actingAs(User::factory()->create())->post(route('threads.connect'));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://threads.net/oauth/authorize?', $location);
        $this->assertSame('test-app-id', $query['client_id']);
        $this->assertSame('http://localhost/auth/threads/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('threads_basic,threads_keyword_search', $query['scope']);
        $this->assertNotEmpty($query['state']);
        Http::assertNothingSent();
    }

    /**
     * SPEC-01 AC-1: Inertia visits get a 409 with `X-Inertia-Location` instead of a cross-origin redirect.
     */
    public function test_connect_returns_inertia_location_for_inertia_visits(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('threads.connect'));

        $response->assertConflict();
        $this->assertStringStartsWith('https://threads.net/oauth/authorize?', (string) $response->headers->get('X-Inertia-Location'));
    }

    /**
     * SPEC-01 AC-2, EC-5: every connect creates a new state of at least 128 bits and binds it to the session.
     */
    public function test_each_connect_stores_a_new_random_state_in_the_session(): void
    {
        $this->actingAs(User::factory()->create());

        $first = $this->post(route('threads.connect'));
        $first->assertRedirect();
        parse_str((string) parse_url((string) $first->headers->get('Location'), PHP_URL_QUERY), $firstQuery);

        $second = $this->post(route('threads.connect'));
        $second->assertRedirect();
        parse_str((string) parse_url((string) $second->headers->get('Location'), PHP_URL_QUERY), $secondQuery);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22,}$/', $firstQuery['state']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22,}$/', $secondQuery['state']);
        $this->assertNotSame($firstQuery['state'], $secondQuery['state']);
        $second->assertSessionHas('threads_oauth_state', $secondQuery['state']);
    }

    /**
     * SPEC-01 NFR-12: connect and callback share a limit of 10 requests per minute per user.
     */
    public function test_connect_and_callback_are_throttled_per_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $statuses = [];

        foreach (range(1, 5) as $ignored) {
            $statuses[] = $this->actingAs($user)->post(route('threads.connect'))->getStatusCode();
            $statuses[] = $this->actingAs($user)->get(route('threads.callback'))->getStatusCode();
        }

        $this->assertNotContains(429, $statuses);
        $this->actingAs($user)->get(route('threads.callback'))->assertTooManyRequests();
        $this->actingAs($user)->post(route('threads.connect'))->assertTooManyRequests();
        $this->actingAs($otherUser)->post(route('threads.connect'))->assertRedirect();
    }

    /**
     * SPEC-01 AC-1: a guest cannot start a connection.
     */
    public function test_guests_cannot_start_a_connection(): void
    {
        $this->post(route('threads.connect'))->assertRedirect(route('login'));
        $this->get(route('threads.callback'))->assertRedirect(route('login'));
    }
}
