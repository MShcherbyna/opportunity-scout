<?php

namespace App\Actions\Threads;

use App\Models\User;

class UpdateReplyTemplate
{
    /**
     * Store the user's reply template.
     */
    public function handle(User $user, ?string $replyTemplate): void
    {
        $user->update(['reply_template' => $replyTemplate]);
    }
}
