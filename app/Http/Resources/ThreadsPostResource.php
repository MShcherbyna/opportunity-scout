<?php

namespace App\Http\Resources;

use App\Models\ThreadsPost;
use App\Services\Threads\ThreadsReplyIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThreadsPost
 */
class ThreadsPostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, threadsPostId: string, text: string|null, permalink: string|null, username: string, keyword: string, postedAt: string, foundAt: string, isRelevant: bool, replyUrl: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'threadsPostId' => $this->threads_post_id,
            'text' => $this->text,
            'permalink' => $this->permalink,
            'username' => $this->username,
            'keyword' => $this->keyword,
            'postedAt' => $this->posted_at->toIso8601String(),
            'foundAt' => $this->created_at->toIso8601String(),
            'isRelevant' => $this->is_relevant,
            'replyUrl' => app(ThreadsReplyIntent::class)->url($this->permalink, $request->user()?->reply_template),
        ];
    }
}
