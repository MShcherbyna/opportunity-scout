<?php

namespace App\Models;

use Database\Factories\ThreadsPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $threads_keyword_id
 * @property string $threads_post_id
 * @property string|null $text
 * @property string|null $permalink
 * @property string $username
 * @property string $keyword
 * @property Carbon $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'threads_keyword_id',
    'threads_post_id',
    'text',
    'permalink',
    'username',
    'keyword',
    'posted_at',
])]
class ThreadsPost extends Model
{
    /** @use HasFactory<ThreadsPostFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ThreadsKeyword, $this>
     */
    public function threadsKeyword(): BelongsTo
    {
        return $this->belongsTo(ThreadsKeyword::class);
    }
}
