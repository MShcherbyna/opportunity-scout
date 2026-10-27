<?php

namespace Tests\Feature;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_guest_can_view_the_privacy_policy(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/Privacy'));
    }

    public function test_guest_always_sees_the_dark_theme_regardless_of_saved_cookie(): void
    {
        $this->withCookie('appearance', 'light')
            ->get(route('privacy'))
            ->assertOk()
            ->assertSee('class="dark"', false);
    }

    public function test_authenticated_user_sees_their_saved_appearance_cookie(): void
    {
        $this->actingAs(User::factory()->create())
            ->withCookie('appearance', 'light')
            ->get(route('privacy'))
            ->assertOk()
            ->assertDontSee('class="dark"', false);
    }

    public function test_guest_can_view_the_data_deletion_instructions(): void
    {
        $this->get(route('data-deletion'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('legal/DataDeletion')
                ->where('deletionRequest', null)
                ->where('codeNotFound', false)
            );
    }
}
