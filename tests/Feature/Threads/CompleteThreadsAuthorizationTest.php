<?php

namespace Tests\Feature\Threads;

use App\Actions\Threads\CompleteThreadsAuthorization;
use App\Actions\Threads\ConnectThreadsAccount;
use App\Enums\ThreadsCallbackOutcome;
use App\Models\User;
use App\Services\Threads\ThreadsOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class CompleteThreadsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const STATE = 'expected-state';

    /**
     * R8: a wrong state is reported as a state mismatch even when the provider sent an error.
     */
    public function test_a_state_mismatch_wins_over_an_error_parameter(): void
    {
        $this->neverConnect();
        $session = $this->sessionWithState();

        $outcome = app(CompleteThreadsAuthorization::class)->handle(
            User::factory()->create(),
            $session,
            ['state' => 'forged-state', 'error' => 'access_denied'],
        );

        $this->assertSame(ThreadsCallbackOutcome::StateMismatch, $outcome);
    }

    /**
     * R8: a valid state with an error parameter is access denied, not a malformed-code failure.
     */
    public function test_an_error_parameter_wins_over_a_malformed_code(): void
    {
        $this->neverConnect();
        $session = $this->sessionWithState();

        $outcome = app(CompleteThreadsAuthorization::class)->handle(
            User::factory()->create(),
            $session,
            ['state' => self::STATE, 'error' => 'access_denied', 'code' => str_repeat('a', 3000)],
        );

        $this->assertSame(ThreadsCallbackOutcome::AccessDenied, $outcome);
    }

    /**
     * R8: the stored state is spent by any callback, so a failed one cannot be replayed.
     */
    public function test_the_state_is_consumed_even_when_the_callback_fails(): void
    {
        $this->neverConnect();
        $authorization = app(CompleteThreadsAuthorization::class);
        $user = User::factory()->create();

        $mismatchSession = $this->sessionWithState();
        $authorization->handle($user, $mismatchSession, ['state' => 'forged-state']);
        $this->assertFalse($mismatchSession->has(ThreadsOAuth::STATE_SESSION_KEY));

        $failedSession = $this->sessionWithState();
        $failed = $authorization->handle($user, $failedSession, ['state' => self::STATE, 'code' => '']);
        $this->assertSame(ThreadsCallbackOutcome::ConnectFailed, $failed);
        $this->assertFalse($failedSession->has(ThreadsOAuth::STATE_SESSION_KEY));

        $replay = $authorization->handle($user, $failedSession, ['state' => self::STATE, 'code' => 'valid-code']);
        $this->assertSame(ThreadsCallbackOutcome::StateMismatch, $replay);
    }

    private function neverConnect(): void
    {
        $this->mock(ConnectThreadsAccount::class)->shouldNotReceive('handle');
    }

    private function sessionWithState(): Store
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $session->put(ThreadsOAuth::STATE_SESSION_KEY, self::STATE);

        return $session;
    }
}
