<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use App\Models\ThreadsKeyword;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class ThreadsKeywordTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function keywordsToNormalise(): array
    {
        return [
            'trims, collapses spaces and lower-cases' => ['  Laravel   Developer ', 'laravel developer'],
            'lower-cases cyrillic' => ['Фрілансер', 'фрілансер'],
            'keeps a leading hash' => ['#Remote', '#remote'],
        ];
    }

    /**
     * SPEC-01 AC-34, AC-106, T-7: a keyword is normalised and saved for the user only.
     */
    #[DataProvider('keywordsToNormalise')]
    public function test_adding_a_keyword_normalises_and_saves_it_for_the_user(string $input, string $expected): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->post(route('settings.keywords.store'), ['keyword' => $input]);

        $response->assertRedirect(route('settings.scraping'));
        $response->assertInertiaFlash('toast.type', 'success');
        $response->assertInertiaFlash('toast.message', 'Keyword added.');
        $this->assertDatabaseHas('threads_keywords', ['user_id' => $user->id, 'keyword' => $expected]);
        $this->assertSame(1, $user->threadsKeywords()->count());
        $this->assertSame(0, $otherUser->threadsKeywords()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeywords(): array
    {
        return [
            'empty' => [''],
            'only spaces' => ['   '],
            'bell control character' => ["foo\x07bar"],
            'null byte' => ["foo\x00bar"],
            'longer than 100 characters' => [str_repeat('a', 101)],
        ];
    }

    /**
     * SPEC-01 AC-35, UI-3: empty, control-character and over-long keywords get a message under the field.
     */
    #[DataProvider('invalidKeywords')]
    public function test_keyword_validation_rejects_empty_control_characters_and_over_100_characters(string $keyword): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->post(route('settings.keywords.store'), ['keyword' => $keyword]);

        $response->assertRedirect(route('settings.scraping'));
        $response->assertSessionHasErrors('keyword');
        $this->assertSame(0, $user->threadsKeywords()->count());
    }

    /**
     * SPEC-01 AC-35: 100 characters is the allowed maximum (counted in characters, not bytes).
     */
    public function test_keyword_of_exactly_100_characters_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('settings.keywords.store'), ['keyword' => str_repeat('я', 100)])->assertSessionHasNoErrors();

        $this->assertSame(1, $user->threadsKeywords()->count());
    }

    /**
     * SPEC-01 AC-36: a keyword equal to an existing one after normalisation is rejected with the spec message.
     */
    public function test_duplicate_keyword_after_normalisation_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->makeKeyword($user, 'laravel');

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->post(route('settings.keywords.store'), ['keyword' => '  LARAVEL ']);

        $response->assertSessionHasErrors(['keyword' => 'This keyword already exists.']);
        $this->assertSame(1, $user->threadsKeywords()->count());
    }

    /**
     * SPEC-01 AC-34, AC-36: keywords are unique per user, so another user may have the same keyword.
     */
    public function test_same_keyword_for_another_user_is_accepted(): void
    {
        $owner = User::factory()->create();
        $this->makeKeyword($owner, 'laravel');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('settings.keywords.store'), ['keyword' => 'laravel'])->assertSessionHasNoErrors();

        $this->assertSame(1, $user->threadsKeywords()->count());
    }

    /**
     * SPEC-01 AC-36 (data model): keywords are compared byte for byte, so `cafe` and `café` are different.
     */
    public function test_keyword_comparison_is_byte_for_byte(): void
    {
        $user = User::factory()->create();
        $this->makeKeyword($user, 'cafe');

        $this->actingAs($user)->post(route('settings.keywords.store'), ['keyword' => 'café'])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['cafe', 'café'], $user->threadsKeywords()->pluck('keyword')->all());
    }

    /**
     * SPEC-01 AC-37, D-3: the cap is 6 at 5 minutes and 20 otherwise; the message names the cap and interval.
     */
    #[DataProvider('capsPerInterval')]
    public function test_keyword_cap_depends_on_the_search_interval(int $interval, int $cap): void
    {
        $user = User::factory()->create(['search_interval_minutes' => $interval]);
        ThreadsKeyword::factory()->count($cap)->for($user)->create();

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->post(route('settings.keywords.store'), ['keyword' => 'one too many']);

        $response->assertSessionHasErrors([
            'keyword' => "You can have up to {$cap} keywords with a {$interval}-minute search interval.",
        ]);
        $this->assertSame($cap, $user->threadsKeywords()->count());
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function capsPerInterval(): array
    {
        return [
            '5 minutes allows 6' => [5, 6],
            '15 minutes allows 20' => [15, 20],
            '30 minutes allows 20' => [30, 20],
            '60 minutes allows 20' => [60, 20],
        ];
    }

    /**
     * SPEC-01 AC-37: one keyword below the cap is still accepted.
     */
    public function test_keyword_below_the_cap_is_accepted(): void
    {
        $user = User::factory()->create(['search_interval_minutes' => 5]);
        ThreadsKeyword::factory()->count(5)->for($user)->create();

        $this->actingAs($user)->post(route('settings.keywords.store'), ['keyword' => 'sixth'])->assertSessionHasNoErrors();

        $this->assertSame(6, $user->threadsKeywords()->count());
    }

    /**
     * SPEC-01 AC-49, D-6: a new keyword's window starts at the time of the change minus the lookback.
     */
    #[DataProvider('lookbacks')]
    public function test_new_keyword_window_starts_at_now_minus_the_lookback(int $days, string $expectedStart): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create(['first_search_lookback_days' => $days]);

        $this->actingAs($user)->post(route('settings.keywords.store'), ['keyword' => 'laravel']);

        $this->assertSame(1, $user->threadsKeywords()->count());
        $keyword = $user->threadsKeywords()->firstOrFail();
        $this->assertSame($expectedStart, $keyword->search_window_start->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($keyword->last_searched_at);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function lookbacks(): array
    {
        return [
            'no lookback' => [0, '2026-10-23 12:00:00'],
            'one day' => [1, '2026-10-22 12:00:00'],
            'three days' => [3, '2026-10-20 12:00:00'],
        ];
    }

    /**
     * SPEC-01 AC-39, AC-49: a rename is normalised, resets the window and the search statistics, and flashes T-7.
     */
    public function test_renaming_applies_normalisation_and_resets_the_window(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create(['first_search_lookback_days' => 3]);
        $keyword = ThreadsKeyword::factory()->for($user)->searched(now()->subHour(), 7)->zeroResultsSince(now()->subDays(8))->create([
            'keyword' => 'old name',
            'search_window_start' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->patch(route('settings.keywords.update', $keyword), ['keyword' => '  New   NAME ']);

        $response->assertRedirect(route('settings.scraping'));
        $response->assertInertiaFlash('toast.message', 'Keyword updated.');
        $stored = $keyword->fresh();
        $this->assertSame('new name', $stored->keyword);
        $this->assertSame('2026-10-20 12:00:00', $stored->search_window_start->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($stored->last_searched_at);
        $this->assertNull($stored->last_result_count);
        $this->assertNull($stored->zero_results_since);
    }

    /**
     * SPEC-01 AC-36, AC-39: saving a keyword under its own value is not a duplicate.
     */
    public function test_renaming_to_its_own_value_is_allowed(): void
    {
        $user = User::factory()->create();
        $keyword = $this->makeKeyword($user, 'alpha');

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->patch(route('settings.keywords.update', $keyword), ['keyword' => 'Alpha']);

        $response->assertRedirect(route('settings.scraping'));
        $response->assertSessionHasNoErrors();
        $response->assertInertiaFlash('toast.message', 'Keyword updated.');
        $this->assertSame('alpha', $keyword->fresh()->keyword);
    }

    /**
     * SPEC-01 AC-36, AC-39, EC-29: renaming to another existing keyword is rejected.
     */
    public function test_renaming_to_another_existing_keyword_is_rejected(): void
    {
        $user = User::factory()->create();
        $keyword = $this->makeKeyword($user, 'alpha');
        $this->makeKeyword($user, 'beta');

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->patch(route('settings.keywords.update', $keyword), ['keyword' => ' BETA ']);

        $response->assertSessionHasErrors(['keyword' => 'This keyword already exists.']);
        $this->assertSame('alpha', $keyword->fresh()->keyword);
    }

    /**
     * SPEC-01 AC-35, AC-39: a rename follows the same validation as adding.
     */
    #[DataProvider('invalidRenames')]
    public function test_renaming_with_an_invalid_value_is_rejected(string $value): void
    {
        $user = User::factory()->create();
        $keyword = $this->makeKeyword($user, 'alpha');

        $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->patch(route('settings.keywords.update', $keyword), ['keyword' => $value])
            ->assertSessionHasErrors('keyword');

        $this->assertSame('alpha', $keyword->fresh()->keyword);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRenames(): array
    {
        return [
            'empty' => [''],
            'control character' => ["foo\x07bar"],
            'longer than 100 characters' => [str_repeat('a', 101)],
        ];
    }

    /**
     * SPEC-01 AC-40, AC-103, D-13: deleting a keyword keeps its posts, with the keyword text and no keyword link.
     */
    public function test_deleting_a_keyword_keeps_its_posts_with_the_keyword_text(): void
    {
        $user = User::factory()->create();
        $keyword = $this->makeKeyword($user, 'laravel');
        $post = $this->makePost($user, ['threads_keyword_id' => $keyword->id, 'keyword' => 'laravel']);

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->delete(route('settings.keywords.destroy', $keyword));

        $response->assertRedirect(route('settings.scraping'));
        $response->assertInertiaFlash('toast.message', 'Keyword removed.');
        $this->assertModelMissing($keyword);
        $this->assertModelExists($post);
        $this->assertSame('laravel', $post->fresh()->keyword);
        $this->assertNull($post->fresh()->threads_keyword_id);
    }

    /**
     * SPEC-01 AC-41, NFR-6: another user's keyword gets 404 and stays unchanged.
     */
    #[DataProvider('writeMethods')]
    public function test_other_users_keyword_returns_404_on_update_and_delete(string $method): void
    {
        $owner = User::factory()->create();
        $keyword = $this->makeKeyword($owner, 'private');
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->{$method}(route('settings.keywords.update', $keyword), ['keyword' => 'hijacked'])
            ->assertNotFound();

        $this->assertSame('private', $keyword->fresh()->keyword);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function writeMethods(): array
    {
        return [
            'rename' => ['patch'],
            'delete' => ['delete'],
        ];
    }

    /**
     * SPEC-01 AC-43: keyword changes are accepted while the Threads connection is disconnected.
     */
    public function test_keywords_can_be_changed_without_a_connection(): void
    {
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->disconnected(ThreadsDisconnectReason::User)->create();

        $this->actingAs($user)->post(route('settings.keywords.store'), ['keyword' => 'laravel'])->assertSessionHasNoErrors();

        $this->assertSame(1, $user->threadsKeywords()->count());
    }

    /**
     * SPEC-01 NFR-12: add, rename and delete share a limit of 30 writes per minute per user.
     */
    public function test_keyword_writes_are_throttled_per_user(): void
    {
        $user = User::factory()->create();
        $statuses = [];

        foreach (range(1, 10) as $ignored) {
            $statuses[] = $this->actingAs($user)->post(route('settings.keywords.store'), ['keyword' => ''])->getStatusCode();
            $statuses[] = $this->actingAs($user)->patch(route('settings.keywords.update', 999_999), ['keyword' => 'x'])->getStatusCode();
            $statuses[] = $this->actingAs($user)->delete(route('settings.keywords.destroy', 999_999))->getStatusCode();
        }

        $this->assertNotContains(429, $statuses);
        $this->actingAs($user)->post(route('settings.keywords.store'), ['keyword' => 'laravel'])->assertTooManyRequests();
        $this->assertSame(0, $user->threadsKeywords()->count());
    }

    /**
     * SPEC-01 AC-36, EC-7 (fix AR-10): a unique violation that appears after validation passed (a concurrent
     * duplicate) is reported as the duplicate-keyword error under the field, not as a server error.
     */
    public function test_store_reports_a_unique_violation_after_validation_as_a_duplicate_keyword(): void
    {
        $user = User::factory()->create();
        ThreadsKeyword::creating(function (): void {
            throw $this->uniqueViolation();
        });

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->post(route('settings.keywords.store'), ['keyword' => 'laravel']);

        $response->assertRedirect(route('settings.scraping'));
        $response->assertSessionHasErrors(['keyword' => 'This keyword already exists.']);
        $this->assertSame(0, $user->threadsKeywords()->count());
    }

    /**
     * SPEC-01 AC-36, EC-7 (fix AR-10): the same for a rename; the keyword keeps its name.
     */
    public function test_update_reports_a_unique_violation_after_validation_as_a_duplicate_keyword(): void
    {
        $user = User::factory()->create();
        $keyword = $this->makeKeyword($user, 'alpha');
        ThreadsKeyword::updating(function (): void {
            throw $this->uniqueViolation();
        });

        $response = $this->actingAs($user)
            ->from(route('settings.scraping'))
            ->patch(route('settings.keywords.update', $keyword), ['keyword' => 'beta']);

        $response->assertRedirect(route('settings.scraping'));
        $response->assertSessionHasErrors(['keyword' => 'This keyword already exists.']);
        $this->assertSame('alpha', $keyword->fresh()->keyword);
    }

    private function uniqueViolation(): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException('mysql', 'insert into threads_keywords', [], new \PDOException('Duplicate entry'));
    }

    /**
     * SPEC-01 NFR-6: guests cannot write keywords.
     */
    public function test_guests_cannot_write_keywords(): void
    {
        $this->post(route('settings.keywords.store'), ['keyword' => 'laravel'])->assertRedirect(route('login'));
    }
}
