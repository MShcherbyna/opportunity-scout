<?php

namespace Tests\Feature\Threads;

use App\Models\ThreadsPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class PostShowTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    /**
     * SPEC-01 AC-79, RT-14: the detail page receives the post in the Post shape.
     */
    public function test_shows_the_users_post(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user, [
            'text' => 'Need a <b>Laravel</b> developer',
            'username' => 'alice',
            'keyword' => 'laravel',
            'permalink' => 'https://www.threads.com/@alice/post/ABC123',
            'posted_at' => '2026-10-23 10:15:00',
        ]);

        $this->actingAs($user)
            ->get(route('posts.show', $post->id))
            ->assertInertia(fn (Assert $page) => $page
                ->component('PostDetail')
                ->missing('postId')
                ->has('post', fn (Assert $props) => $props
                    ->where('id', $post->id)
                    ->where('threadsPostId', $post->threads_post_id)
                    ->where('text', 'Need a <b>Laravel</b> developer')
                    ->where('permalink', 'https://www.threads.com/@alice/post/ABC123')
                    ->where('username', 'alice')
                    ->where('keyword', 'laravel')
                    ->where('postedAt', fn (string $value) => Carbon::parse($value)->utc()->format('Y-m-d H:i:s') === '2026-10-23 10:15:00')
                    ->has('foundAt')
                    ->where('replyUrl', 'https://www.threads.com/intent/post?reply_post_shortcode=ABC123')
                    ->where('isRelevant', false)
                )
            );
    }

    /**
     * SPEC-01 AC-79, AC-117: the detail page carries the post's relevance flag.
     */
    public function test_detail_carries_the_relevance_flag(): void
    {
        $user = User::factory()->create();
        $post = ThreadsPost::factory()->relevant()->create([
            'user_id' => $user->id,
            'threads_keyword_id' => null,
            'keyword' => 'laravel',
        ]);

        $this->actingAs($user)
            ->get(route('posts.show', $post->id))
            ->assertInertia(fn (Assert $page) => $page->where('post.isRelevant', true));
    }

    /**
     * SPEC-01 AC-62, EC-23: a media-only post has null text; a post without a valid permalink has null permalink.
     */
    public function test_media_only_post_without_permalink_has_null_text_and_permalink(): void
    {
        $user = User::factory()->create();
        $post = ThreadsPost::factory()->withoutText()->create([
            'user_id' => $user->id,
            'threads_keyword_id' => null,
            'keyword' => 'laravel',
            'permalink' => null,
        ]);

        $this->actingAs($user)
            ->get(route('posts.show', $post->id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.text', null)
                ->where('post.permalink', null)
                ->where('post.replyUrl', null)
            );
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
     * SPEC-01 AC-80, NFR-6: another user's post and a missing post both return 404.
     */
    #[DataProvider('unreachablePosts')]
    public function test_other_users_or_missing_post_returns_404(string $case): void
    {
        $user = User::factory()->create();
        $postId = $case === 'foreign'
            ? $this->makePost(User::factory()->create())->id
            : 999_999;

        $this->actingAs($user)->get(route('posts.show', $postId))->assertNotFound();
    }

    /**
     * SPEC-01 NFR-6: guests are sent to the login page.
     */
    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $post = $this->makePost(User::factory()->create());

        $this->get(route('posts.show', $post->id))->assertRedirect(route('login'));
    }
}
