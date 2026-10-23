<?php

namespace Tests\Feature\Threads;

use App\Models\ThreadsKeyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SearchSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SPEC-01 AC-70, T-8: the interval and the first-search lookback are stored.
     */
    public function test_search_settings_are_saved(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->patch(route('settings.searchSettings.update'), [
                'interval_minutes' => 30,
                'first_search_lookback_days' => 3,
            ]);

        $response->assertRedirect(route('settings.scraping'));
        $response->assertInertiaFlash('toast.type', 'success');
        $response->assertInertiaFlash('toast.message', 'Search settings saved.');
        $stored = $user->fresh();
        $this->assertSame(30, $stored->search_interval_minutes);
        $this->assertSame(3, $stored->first_search_lookback_days);
    }

    /**
     * SPEC-01 AC-70, D-1, D-6: a new user searches every 15 minutes with a 1 day lookback.
     */
    public function test_new_users_default_to_15_minutes_and_one_day_lookback(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame(15, $user->search_interval_minutes);
        $this->assertSame(1, $user->first_search_lookback_days);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidSettings(): array
    {
        $valid = ['interval_minutes' => 15, 'first_search_lookback_days' => 1];

        return [
            'interval not in the allowed set' => [[...$valid, 'interval_minutes' => 10], 'interval_minutes'],
            'zero interval' => [[...$valid, 'interval_minutes' => 0], 'interval_minutes'],
            'text interval' => [[...$valid, 'interval_minutes' => 'often'], 'interval_minutes'],
            'missing interval' => [['first_search_lookback_days' => 1], 'interval_minutes'],
            'lookback not in the allowed set' => [[...$valid, 'first_search_lookback_days' => 2], 'first_search_lookback_days'],
            'negative lookback' => [[...$valid, 'first_search_lookback_days' => -1], 'first_search_lookback_days'],
            'missing lookback' => [['interval_minutes' => 15], 'first_search_lookback_days'],
        ];
    }

    /**
     * SPEC-01 UI-9: values outside the allowed sets are rejected with HTTP 422 and nothing is stored.
     *
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidSettings')]
    public function test_interval_and_lookback_outside_the_allowed_sets_are_rejected(array $payload, string $errorKey): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('settings.searchSettings.update'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);

        $stored = $user->fresh();
        $this->assertSame(15, $stored->search_interval_minutes);
        $this->assertSame(1, $stored->first_search_lookback_days);
    }

    /**
     * SPEC-01 AC-44: an interval whose cap is below the keyword count is rejected with the spec message.
     */
    public function test_interval_with_a_cap_below_the_keyword_count_is_rejected_with_the_spec_message(): void
    {
        $user = User::factory()->create();
        ThreadsKeyword::factory()->count(7)->for($user)->create();

        $this->actingAs($user)
            ->patchJson(route('settings.searchSettings.update'), ['interval_minutes' => 5, 'first_search_lookback_days' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['interval_minutes' => 'A 5-minute interval allows up to 6 keywords. Remove keywords first.']);

        $this->assertSame(15, $user->fresh()->search_interval_minutes);
    }

    /**
     * SPEC-01 AC-44: a keyword count equal to the cap is allowed.
     */
    public function test_interval_with_a_cap_equal_to_the_keyword_count_is_accepted(): void
    {
        $user = User::factory()->create();
        ThreadsKeyword::factory()->count(6)->for($user)->create();

        $this->actingAs($user)
            ->patch(route('settings.searchSettings.update'), ['interval_minutes' => 5, 'first_search_lookback_days' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $user->fresh()->search_interval_minutes);
    }

    /**
     * SPEC-01 NFR-6: guests cannot change search settings.
     */
    public function test_guests_cannot_change_search_settings(): void
    {
        $this->patch(route('settings.searchSettings.update'), ['interval_minutes' => 30, 'first_search_lookback_days' => 0])
            ->assertRedirect(route('login'));
    }
}
