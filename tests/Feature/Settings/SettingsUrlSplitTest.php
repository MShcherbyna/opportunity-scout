<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Literal URLs are used on purpose: the URL split between `/account/*`
 * (account pages) and `/settings/*` (scout settings) is the contract under test.
 */
class SettingsUrlSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_visiting_account_root_is_redirected_to_login(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
    }

    public function test_account_root_redirects_to_profile_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/account')
            ->assertRedirect('/account/profile');
    }

    public function test_profile_page_renders_under_account_url(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/account/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('account/Profile'));
    }

    public function test_settings_root_redirects_to_scraping_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings')
            ->assertRedirect('/settings/scraping');
    }

    public function test_scraping_settings_page_renders_under_settings_url(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings/scraping')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scout-settings/Scraping')
                ->has('keywords')
                ->has('searchSettings')
                ->has('searchBudget')
                ->has('ownPostsOnly')
            );
    }

    public function test_old_account_settings_url_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings/profile')
            ->assertNotFound();
    }

    public function test_passkey_well_known_endpoints_point_to_account_security(): void
    {
        $this->get(route('well-known.passkeys'))
            ->assertExactJson([
                'enroll' => url('/account/security'),
                'manage' => url('/account/security'),
            ]);
    }
}
