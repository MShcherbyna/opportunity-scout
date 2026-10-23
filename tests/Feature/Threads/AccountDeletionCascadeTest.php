<?php

namespace Tests\Feature\Threads;

use App\Models\ThreadsConnection;
use App\Models\ThreadsKeyword;
use App\Models\ThreadsPost;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class AccountDeletionCascadeTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    /**
     * SPEC-01 MC-4: deleting the Scout account removes its connection, keywords, posts and runs; other users keep theirs.
     */
    public function test_deleting_the_account_removes_connection_keywords_posts_and_runs(): void
    {
        $user = User::factory()->create();
        $connection = ThreadsConnection::factory()->for($user)->create();
        $keyword = $this->makeKeyword($user, 'laravel');
        $post = $this->makePost($user, ['threads_keyword_id' => $keyword->id]);
        $run = ThreadsSearchRun::factory()->for($user)->create();
        $other = User::factory()->create();
        $otherKeyword = $this->makeKeyword($other, 'laravel');
        $otherPost = $this->makePost($other);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

        $this->assertModelMissing($user);
        $this->assertModelMissing($connection);
        $this->assertModelMissing($keyword);
        $this->assertModelMissing($post);
        $this->assertModelMissing($run);
        $this->assertModelExists($otherKeyword);
        $this->assertModelExists($otherPost);
        $this->assertSame(1, ThreadsKeyword::query()->count());
        $this->assertSame(1, ThreadsPost::query()->count());
    }
}
