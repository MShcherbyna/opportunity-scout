<?php

namespace Tests\Feature\Threads;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

/**
 * SPEC-01 AC-116, Post shape: server-built `replyUrl` on a found post.
 */
class PostReplyUrlTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    /**
     * SPEC-01 AC-116: without a saved reply template, replyUrl carries only the shortcode, no `text` parameter.
     */
    public function test_reply_url_targets_the_reply_intent_with_the_permalink_shortcode(): void
    {
        $user = User::factory()->create(['reply_template' => null]);
        $post = $this->makePost($user, ['permalink' => 'https://www.threads.com/@alice/post/ABC123']);

        $this->actingAs($user)
            ->get(route('posts.show', $post->id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.replyUrl', 'https://www.threads.com/intent/post?reply_post_shortcode=ABC123')
            );
    }

    /**
     * SPEC-01 AC-116, AC-115: the saved reply template is appended as `text`, RFC 3986 percent-encoded.
     */
    public function test_reply_url_includes_the_percent_encoded_reply_template(): void
    {
        $user = User::factory()->create([
            'reply_template' => "Thanks for sharing, I'd love to help — DM me! #php & more?",
        ]);
        $post = $this->makePost($user, ['permalink' => 'https://www.threads.com/@alice/post/ABC123']);

        $this->actingAs($user)
            ->get(route('posts.show', $post->id))
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'post.replyUrl',
                    'https://www.threads.com/intent/post?reply_post_shortcode=ABC123'
                        .'&text=Thanks%20for%20sharing%2C%20I%27d%20love%20to%20help%20%E2%80%94%20DM%20me%21%20%23php%20%26%20more%3F'
                )
            );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function permalinksWithTrailingNoise(): array
    {
        return [
            'trailing slash' => ['https://www.threads.com/@alice/post/ABC123/'],
            'query string' => ['https://www.threads.com/@alice/post/ABC123?xmt=1'],
        ];
    }

    /**
     * SPEC-01 AC-116, Post shape: the shortcode is the last non-empty path segment; a trailing slash or query string does not change it.
     */
    #[DataProvider('permalinksWithTrailingNoise')]
    public function test_reply_url_uses_the_last_non_empty_path_segment(string $permalink): void
    {
        $user = User::factory()->create(['reply_template' => null]);
        $post = $this->makePost($user, ['permalink' => $permalink]);

        $this->actingAs($user)
            ->get(route('posts.show', $post->id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.replyUrl', 'https://www.threads.com/intent/post?reply_post_shortcode=ABC123')
            );
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function unusablePermalinks(): array
    {
        return [
            'no permalink' => [null],
            'host only, no path' => ['https://www.threads.com'],
            'host with trailing slash only' => ['https://www.threads.com/'],
        ];
    }

    /**
     * SPEC-01 Post shape, UI-5, Q2: replyUrl is null without a permalink, or when the permalink's path has no usable last segment.
     */
    #[DataProvider('unusablePermalinks')]
    public function test_reply_url_is_null_without_a_usable_shortcode(?string $permalink): void
    {
        $user = User::factory()->create(['reply_template' => null]);
        $post = $this->makePost($user, ['permalink' => $permalink]);

        $this->actingAs($user)
            ->get(route('posts.show', $post->id))
            ->assertInertia(fn (Assert $page) => $page->where('post.replyUrl', null));
    }
}
