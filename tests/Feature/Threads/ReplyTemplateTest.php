<?php

namespace Tests\Feature\Threads;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReplyTemplateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SPEC-01 AC-115, RT-17, T-12: a reply template is stored for the signed-in user.
     */
    public function test_reply_template_is_saved(): void
    {
        $user = User::factory()->create(['reply_template' => null]);

        $response = $this->actingAs($user)
            ->from(route('settings.replyTemplate.show'))
            ->patch(route('settings.replyTemplate.update'), [
                'reply_template' => 'Thanks for sharing, DM me!',
            ]);

        $response->assertRedirect(route('settings.replyTemplate.show'));
        $response->assertInertiaFlash('toast.type', 'success');
        $response->assertInertiaFlash('toast.message', 'Reply template saved.');
        $this->assertSame('Thanks for sharing, DM me!', $user->fresh()->reply_template);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function emptyTemplates(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace only' => ['   '],
        ];
    }

    /**
     * SPEC-01 AC-115: saving an empty value clears the stored template.
     */
    #[DataProvider('emptyTemplates')]
    public function test_empty_reply_template_clears_the_saved_template(?string $value): void
    {
        $user = User::factory()->create(['reply_template' => 'Previously saved']);

        $this->actingAs($user)
            ->patch(route('settings.replyTemplate.update'), ['reply_template' => $value])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->reply_template);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fiveHundredCharacterTemplates(): array
    {
        return [
            'ascii' => [str_repeat('a', 500)],
            'multibyte' => [str_repeat('é', 500)],
        ];
    }

    /**
     * SPEC-01 AC-115: the limit is 500 characters, counted by mb_strlen, not bytes.
     */
    #[DataProvider('fiveHundredCharacterTemplates')]
    public function test_reply_template_of_exactly_500_characters_is_accepted(string $value): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.replyTemplate.update'), ['reply_template' => $value])
            ->assertSessionHasNoErrors();

        $this->assertSame($value, $user->fresh()->reply_template);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidTemplates(): array
    {
        return [
            '501 characters' => [str_repeat('a', 501)],
            'array value' => [['not a string']],
        ];
    }

    /**
     * SPEC-01 RT-17: an overlong or non-string value is rejected with HTTP 422 and nothing is stored.
     */
    #[DataProvider('invalidTemplates')]
    public function test_invalid_reply_template_is_rejected(mixed $value): void
    {
        $user = User::factory()->create(['reply_template' => null]);

        $this->actingAs($user)
            ->patchJson(route('settings.replyTemplate.update'), ['reply_template' => $value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reply_template');

        $this->assertNull($user->fresh()->reply_template);
    }

    /**
     * SPEC-01 NFR-6: a user cannot change another user's reply template.
     */
    public function test_reply_template_is_saved_only_for_the_signed_in_user(): void
    {
        $other = User::factory()->create(['reply_template' => 'Keep mine']);
        $user = User::factory()->create(['reply_template' => null]);

        $this->actingAs($user)
            ->patch(route('settings.replyTemplate.update'), ['reply_template' => 'My own template'])
            ->assertSessionHasNoErrors();

        $this->assertSame('My own template', $user->fresh()->reply_template);
        $this->assertSame('Keep mine', $other->fresh()->reply_template);
    }

    /**
     * SPEC-01 RT-17: guests cannot change the reply template.
     */
    public function test_guests_cannot_change_the_reply_template(): void
    {
        $this->patch(route('settings.replyTemplate.update'), ['reply_template' => 'Hello'])
            ->assertRedirect(route('login'));
    }
}
