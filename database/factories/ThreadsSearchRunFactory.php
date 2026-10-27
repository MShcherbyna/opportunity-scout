<?php

namespace Database\Factories;

use App\Enums\ThreadsSearchRunOutcome;
use App\Enums\ThreadsSearchRunTrigger;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThreadsSearchRun>
 */
class ThreadsSearchRunFactory extends Factory
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
            'trigger' => ThreadsSearchRunTrigger::Scheduled,
            'queued_at' => now()->subMinutes(5),
            'started_at' => now()->subMinutes(4),
            'finished_at' => now()->subMinutes(3),
            'queries_sent' => 0,
            'posts_stored' => 0,
            'outcome' => ThreadsSearchRunOutcome::Completed,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (): array => [
            'queued_at' => now(),
            'started_at' => null,
            'finished_at' => null,
            'outcome' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'queued_at' => now()->subMinute(),
            'started_at' => now(),
            'finished_at' => null,
            'outcome' => null,
        ]);
    }

    public function finished(ThreadsSearchRunOutcome $outcome = ThreadsSearchRunOutcome::Completed): static
    {
        return $this->state(fn (): array => [
            'finished_at' => now(),
            'outcome' => $outcome,
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (): array => ['trigger' => ThreadsSearchRunTrigger::Manual]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['trigger' => ThreadsSearchRunTrigger::Scheduled]);
    }

    public function startedAt(DateTimeInterface $startedAt): static
    {
        return $this->state(fn (): array => ['started_at' => $startedAt]);
    }
}
