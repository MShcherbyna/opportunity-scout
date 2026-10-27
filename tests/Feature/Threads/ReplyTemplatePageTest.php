<?php

namespace Tests\Feature\Threads;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReplyTemplatePageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SPEC-01 RT-19, AC-115, MC-11, D-32: the page receives the user's saved reply template.
     */
    #[DataProvider('replyTemplates')]
    public function test_reply_template_page_passes_the_reply_template(?string $saved): void
    {
        $user = User::factory()->create(['reply_template' => $saved]);

        $this->actingAs($user)
            ->get(route('settings.replyTemplate.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('scout-settings/ReplyTemplate')
                ->where('replyTemplate', $saved)
            );
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function replyTemplates(): array
    {
        return [
            'saved template' => ['Thanks for reaching out, I\'d love to help!'],
            'never saved' => [null],
        ];
    }
}
