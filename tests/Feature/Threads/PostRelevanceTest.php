<?php

namespace Tests\Feature\Threads;

use App\Models\ThreadsPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

/**
 * SPEC-01 AC-117, RT-18: setting a found post's relevance flag.
 */
class PostRelevanceTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    /**
     * SPEC-01 AC-117, RT-18: marking a post relevant sets the flag and redirects back (303), with no toast.
     */
    public function test_marking_a_post_relevant_sets_the_flag_and_redirects_back(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user);

        $response = $this->actingAs($user)
            ->from(route('posts.index'))
            ->patch(route('posts.relevant.update', $post), ['is_relevant' => true]);

        $response->assertRedirect(route('posts.index'));
        $response->assertStatus(303);
        $response->assertInertiaFlashMissing('toast');
        $this->assertTrue($post->fresh()->is_relevant);
    }

    /**
     * SPEC-01 AC-117: unmarking a relevant post clears the flag.
     */
    public function test_unmarking_a_post_clears_the_flag(): void
    {
        $user = User::factory()->create();
        $post = ThreadsPost::factory()->relevant()->create([
            'user_id' => $user->id,
            'threads_keyword_id' => null,
            'keyword' => 'laravel',
        ]);

        $response = $this->actingAs($user)
            ->from(route('posts.index'))
            ->patch(route('posts.relevant.update', $post), ['is_relevant' => false]);

        $response->assertRedirect(route('posts.index'));
        $this->assertFalse($post->fresh()->is_relevant);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function relevanceStates(): array
    {
        return [
            'true twice' => [true],
            'false twice' => [false],
        ];
    }

    /**
     * SPEC-01 AC-117, decision 4: repeating the same wanted state twice is a no-op, not a flip.
     */
    #[DataProvider('relevanceStates')]
    public function test_repeating_the_same_state_does_not_flip_it(bool $state): void
    {
        $user = User::factory()->create();
        // Start from the opposite value so a missing write is visible: the flag must first flip to $state.
        $post = $this->makePost($user, ['is_relevant' => ! $state]);

        $this->actingAs($user)
            ->from(route('posts.index'))
            ->patch(route('posts.relevant.update', $post), ['is_relevant' => $state]);
        $this->actingAs($user)
            ->from(route('posts.index'))
            ->patch(route('posts.relevant.update', $post), ['is_relevant' => $state]);

        $this->assertSame($state, $post->fresh()->is_relevant);
    }

    /**
     * SPEC-01 AC-117: only the targeted post changes, not another post of the same user.
     */
    public function test_only_the_target_post_changes(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user, ['is_relevant' => false]);
        $other = $this->makePost($user, ['is_relevant' => false]);

        $this->actingAs($user)
            ->from(route('posts.index'))
            ->patch(route('posts.relevant.update', $post), ['is_relevant' => true]);

        $this->assertTrue($post->fresh()->is_relevant);
        $this->assertFalse($other->fresh()->is_relevant);
    }

    /**
     * `null` marks "send no `is_relevant` key at all" (missing); every other value is sent as-is.
     *
     * @return array<string, array{mixed}>
     */
    public static function invalidRelevanceValues(): array
    {
        return [
            'missing' => [null],
            'non-boolean string' => ['yes'],
            'non-boolean integer' => [2],
            'array' => [['not' => 'boolean']],
        ];
    }

    /**
     * SPEC-01 RT-18: a missing or non-boolean `is_relevant` is rejected with HTTP 422 and the flag is unchanged.
     *
     * Expected to already pass after Step 0: the Form Request rules ship with the skeleton (decision 4).
     */
    #[DataProvider('invalidRelevanceValues')]
    public function test_invalid_is_relevant_is_rejected(mixed $value): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user, ['is_relevant' => false]);
        $body = $value === null ? [] : ['is_relevant' => $value];

        $this->actingAs($user)
            ->patchJson(route('posts.relevant.update', $post), $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_relevant');

        $this->assertFalse($post->fresh()->is_relevant);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreachablePosts(): array
    {
        return [
            'post of another user' => ['foreign'],
            'post that does not exist' => ['missing'],
        ];
    }

    /**
     * SPEC-01 RT-18, AC-80 pattern, NFR-6: another user's post and a missing post both return 404; a foreign post's flag is unchanged.
     */
    #[DataProvider('unreachablePosts')]
    public function test_another_users_or_missing_post_returns_404(string $case): void
    {
        $user = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $foreignPost = $this->makePost($foreignOwner, ['is_relevant' => false]);
        $postId = $case === 'foreign' ? $foreignPost->id : 999_999;

        $this->actingAs($user)
            ->patch(route('posts.relevant.update', $postId), ['is_relevant' => true])
            ->assertNotFound();

        $this->assertFalse($foreignPost->fresh()->is_relevant);
    }

    /**
     * SPEC-01 RT-18: guests cannot change a post's relevance.
     */
    public function test_guests_cannot_change_relevance(): void
    {
        $owner = User::factory()->create();
        $post = $this->makePost($owner, ['is_relevant' => false]);

        $this->patch(route('posts.relevant.update', $post), ['is_relevant' => true])
            ->assertRedirect(route('login'));

        $this->assertFalse($post->fresh()->is_relevant);
    }
}
