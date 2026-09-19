<?php

namespace Tests\Feature;

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

    public function test_guest_can_view_the_data_deletion_instructions(): void
    {
        $this->get(route('data-deletion'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/DataDeletion'));
    }
}
