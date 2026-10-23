<?php

namespace Database\Factories;

use App\Models\ThreadsKeyword;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThreadsKeyword>
 */
class ThreadsKeywordFactory extends Factory
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
            'keyword' => fake()->unique()->words(2, true),
            'search_window_start' => now()->subDay(),
            'last_searched_at' => null,
            'last_result_count' => null,
            'zero_results_since' => null,
        ];
    }

    public function searched(?DateTimeInterface $at = null, int $resultCount = 3): static
    {
        return $this->state(fn (): array => [
            'last_searched_at' => $at ?? now()->subMinutes(10),
            'last_result_count' => $resultCount,
        ]);
    }

    public function zeroResultsSince(DateTimeInterface $since): static
    {
        return $this->state(fn (): array => [
            'last_result_count' => 0,
            'zero_results_since' => $since,
        ]);
    }
}
