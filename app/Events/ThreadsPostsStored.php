<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ThreadsPostsStored
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $postIds
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $postIds,
    ) {}
}
