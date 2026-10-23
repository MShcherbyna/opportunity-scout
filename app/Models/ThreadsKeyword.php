<?php

namespace App\Models;

use Database\Factories\ThreadsKeywordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $keyword
 * @property Carbon $search_window_start
 * @property Carbon|null $last_searched_at
 * @property int|null $last_result_count
 * @property Carbon|null $zero_results_since
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'keyword',
    'search_window_start',
    'last_searched_at',
    'last_result_count',
    'zero_results_since',
])]
class ThreadsKeyword extends Model
{
    /** @use HasFactory<ThreadsKeywordFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'search_window_start' => 'datetime',
            'last_searched_at' => 'datetime',
            'last_result_count' => 'integer',
            'zero_results_since' => 'datetime',
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
     * @return HasMany<ThreadsPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ThreadsPost::class);
    }
}
