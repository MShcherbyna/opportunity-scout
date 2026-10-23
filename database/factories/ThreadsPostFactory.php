<?php

namespace Database\Factories;

use App\Models\ThreadsKeyword;
use App\Models\ThreadsPost;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThreadsPost>
 */
class ThreadsPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = fake()->userName();

        return [
            'user_id' => User::factory(),
            'threads_keyword_id' => fn (array $attributes) => ThreadsKeyword::factory()->create(['user_id' => $attributes['user_id']])->id,
            'threads_post_id' => (string) fake()->unique()->numerify('1###############'),
            'text' => fake()->sentence(),
            'permalink' => 'https://www.threads.com/@'.$username.'/post/'.fake()->bothify('???###???'),
            'username' => $username,
            'keyword' => fn (array $attributes) => ThreadsKeyword::query()->whereKey($attributes['threads_keyword_id'])->value('keyword') ?? fake()->word(),
            'posted_at' => now()->subHours(2),
        ];
    }

    public function withoutText(): static
    {
        return $this->state(fn (): array => ['text' => null]);
    }

    public function foundAt(DateTimeInterface $foundAt): static
    {
        return $this->state(fn (): array => [
            'created_at' => $foundAt,
            'updated_at' => $foundAt,
        ]);
    }
}
