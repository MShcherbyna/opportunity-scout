<?php

namespace App\Console\Commands;

use App\Models\ThreadsPost;
use App\Models\ThreadsSearchRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('threads:prune-posts')]
#[Description('Delete old Threads posts and search runs')]
class PruneThreadsPosts extends Command
{
    private const POST_RETENTION_DAYS = 90;

    private const RUN_RETENTION_DAYS = 7;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $postsDeleted = ThreadsPost::query()
            ->where('created_at', '<', now()->subDays(self::POST_RETENTION_DAYS))
            ->delete();

        $runsDeleted = ThreadsSearchRun::query()
            ->where('queued_at', '<', now()->subDays(self::RUN_RETENTION_DAYS))
            ->delete();

        Log::info('Threads data pruned.', [
            'posts_deleted' => $postsDeleted,
            'runs_deleted' => $runsDeleted,
        ]);

        return self::SUCCESS;
    }
}
