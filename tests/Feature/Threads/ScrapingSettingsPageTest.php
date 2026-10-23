<?php

namespace Tests\Feature\Threads;

use App\Models\ThreadsKeyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class ScrapingSettingsPageTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    /**
     * SPEC-01 AC-95, AC-92, NFR-6, MC-2: the page lists only the user's keywords, each with its search status.
     */
    public function test_scraping_page_lists_only_the_users_keywords_with_search_status(): void
    {
        $user = User::factory()->create();
        $searched = ThreadsKeyword::factory()->for($user)->searched(Carbon::parse('2026-10-22 08:00:00'), 4)->create(['keyword' => 'laravel']);
        $neverSearched = ThreadsKeyword::factory()->for($user)->create(['keyword' => 'php']);
        $silent = ThreadsKeyword::factory()->for($user)->zeroResultsSince(Carbon::parse('2026-10-10 00:00:00'))->create(['keyword' => 'remote']);
        ThreadsKeyword::factory()->create(['keyword' => 'someone elses']);

        $response = $this->actingAs($user)->get(route('settings.scraping'));

        $keywords = collect($response->requiredProp('keywords'))->keyBy('id');
        $this->assertCount(3, $keywords);
        $this->assertEqualsCanonicalizing(
            ['id', 'keyword', 'lastSearchedAt', 'lastResultCount', 'zeroResultsSince'],
            array_keys($keywords[$searched->id]),
        );
        $this->assertSame('laravel', $keywords[$searched->id]['keyword']);
        $this->assertSame(4, $keywords[$searched->id]['lastResultCount']);
        $this->assertSame('2026-10-22 08:00:00', Carbon::parse($keywords[$searched->id]['lastSearchedAt'])->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($keywords[$neverSearched->id]['lastSearchedAt']);
        $this->assertNull($keywords[$neverSearched->id]['lastResultCount']);
        $this->assertNull($keywords[$neverSearched->id]['zeroResultsSince']);
        $this->assertSame(0, $keywords[$silent->id]['lastResultCount']);
        $this->assertSame('2026-10-10 00:00:00', Carbon::parse($keywords[$silent->id]['zeroResultsSince'])->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 RT-8: the page receives the stored interval and lookback.
     */
    public function test_scraping_page_passes_the_search_settings(): void
    {
        $user = User::factory()->create(['search_interval_minutes' => 30, 'first_search_lookback_days' => 3]);

        $this->actingAs($user)
            ->get(route('settings.scraping'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('scout-settings/Scraping')
                ->where('searchSettings.intervalMinutes', 30)
                ->where('searchSettings.firstSearchLookbackDays', 3)
            );
    }

    /**
     * @return array<string, array{int, int, int, int, int}>
     */
    public static function budgets(): array
    {
        return [
            '5 minutes with 4 keywords' => [5, 4, 1152, 288, 6],
            '15 minutes with 3 keywords' => [15, 3, 288, 96, 20],
            '30 minutes with 2 keywords' => [30, 2, 96, 48, 20],
            '60 minutes with 1 keyword' => [60, 1, 24, 24, 20],
        ];
    }

    /**
     * SPEC-01 AC-42, AC-96: planned queries, runs per day, cap and keyword count follow the budget table.
     */
    #[DataProvider('budgets')]
    public function test_search_budget_shows_planned_queries_runs_per_day_and_cap(int $interval, int $keywordCount, int $planned, int $runsPerDay, int $cap): void
    {
        $user = User::factory()->create(['search_interval_minutes' => $interval]);
        ThreadsKeyword::factory()->count($keywordCount)->for($user)->create();

        $this->actingAs($user)
            ->get(route('settings.scraping'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('searchBudget.dailyBudget', 2000)
                ->where('searchBudget.plannedDailyQueries', $planned)
                ->where('searchBudget.runsPerDay', $runsPerDay)
                ->where('searchBudget.keywordCap', $cap)
                ->where('searchBudget.keywordCount', $keywordCount)
            );
    }

    /**
     * SPEC-01 AC-90, NFR-3: the own-posts-only flag comes from the configuration.
     */
    #[DataProvider('ownPostsOnlyFlags')]
    public function test_own_posts_only_flag_is_passed_from_config(bool $enabled): void
    {
        config(['services.threads.own_posts_only' => $enabled]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings.scraping'))
            ->assertInertia(fn (Assert $page) => $page->where('ownPostsOnly', $enabled));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function ownPostsOnlyFlags(): array
    {
        return [
            'enabled' => [true],
            'disabled' => [false],
        ];
    }
}
