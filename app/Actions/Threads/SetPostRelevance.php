<?php

namespace App\Actions\Threads;

use App\Models\ThreadsPost;

class SetPostRelevance
{
    /**
     * Set the post's relevance flag to the given state.
     */
    public function handle(ThreadsPost $post, bool $isRelevant): void
    {
        $post->update(['is_relevant' => $isRelevant]);
    }
}
