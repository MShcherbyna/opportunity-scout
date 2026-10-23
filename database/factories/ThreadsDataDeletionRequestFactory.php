<?php

namespace Database\Factories;

use App\Enums\ThreadsDataDeletionStatus;
use App\Models\ThreadsDataDeletionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ThreadsDataDeletionRequest>
 */
class ThreadsDataDeletionRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'confirmation_code' => Str::random(16),
            'status' => ThreadsDataDeletionStatus::Completed,
            'requested_at' => now(),
            'completed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => ThreadsDataDeletionStatus::Pending,
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ThreadsDataDeletionStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
