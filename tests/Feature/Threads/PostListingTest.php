<?php

namespace Tests\Feature\Threads;

use App\Models\User;
use App\Services\Threads\PostListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class PostListingTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    /**
     * R8: a page past the end shows the last page instead of an empty one.
     */
    public function test_a_page_beyond_the_last_page_is_clamped_to_the_last_page(): void
    {
        $user = User::factory()->create();

        for ($index = 0; $index < PostListing::PER_PAGE + 3; $index++) {
            $this->makePost($user);
        }

        $posts = app(PostListing::class)->paginate($user, '', null, 99);

        $this->assertSame(2, $posts->currentPage());
        $this->assertSame(2, $posts->lastPage());
        $this->assertSame(PostListing::PER_PAGE + 3, $posts->total());
        $this->assertCount(3, $posts->items());
    }

    /**
     * R8: no matching posts is page 1 of 1, even when a later page was requested.
     */
    public function test_an_empty_result_is_page_one_of_one(): void
    {
        $user = User::factory()->create();
        $this->makePost(User::factory()->create());

        $posts = app(PostListing::class)->paginate($user, '', null, 5);

        $this->assertSame(1, $posts->currentPage());
        $this->assertSame(1, $posts->lastPage());
        $this->assertSame(0, $posts->total());
        $this->assertCount(0, $posts->items());
    }
}
