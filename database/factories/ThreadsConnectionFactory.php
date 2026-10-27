<?php

namespace Database\Factories;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ThreadsConnection>
 */
class ThreadsConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'threads_user_id' => (string) fake()->unique()->numerify('##############'),
            'username' => fake()->unique()->userName(),
            'name' => fake()->name(),
            'access_token' => Str::random(120),
            'token_issued_at' => now()->subDays(5),
            'token_expires_at' => now()->addDays(55),
            'status' => ThreadsConnectionStatus::Connected,
            'disconnect_reason' => null,
            'search_paused_until' => null,
            'connected_at' => now()->subDays(5),
            'disconnected_at' => null,
        ];
    }

    /**
     * Indicate that the connection was disconnected and holds no token.
     */
    public function disconnected(ThreadsDisconnectReason $reason = ThreadsDisconnectReason::User): static
    {
        return $this->state(fn (): array => [
            'access_token' => null,
            'status' => ThreadsConnectionStatus::Disconnected,
            'disconnect_reason' => $reason,
            'disconnected_at' => now(),
            'search_paused_until' => null,
        ]);
    }

    public function expiresAt(DateTimeInterface $expiresAt): static
    {
        return $this->state(fn (): array => ['token_expires_at' => $expiresAt]);
    }

    public function issuedAt(DateTimeInterface $issuedAt): static
    {
        return $this->state(fn (): array => ['token_issued_at' => $issuedAt]);
    }

    public function paused(?DateTimeInterface $until = null): static
    {
        return $this->state(fn (): array => ['search_paused_until' => $until ?? now()->addHour()]);
    }
}
