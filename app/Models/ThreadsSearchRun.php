<?php

namespace App\Models;

use App\Enums\ThreadsSearchRunOutcome;
use App\Enums\ThreadsSearchRunTrigger;
use Database\Factories\ThreadsSearchRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property ThreadsSearchRunTrigger $trigger
 * @property Carbon $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int $queries_sent
 * @property int $posts_stored
 * @property ThreadsSearchRunOutcome|null $outcome
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'trigger',
    'queued_at',
    'started_at',
    'finished_at',
    'queries_sent',
    'posts_stored',
    'outcome',
])]
class ThreadsSearchRun extends Model
{
    /** @use HasFactory<ThreadsSearchRunFactory> */
    use HasFactory;

    /**
     * A queued run counts as active for this many minutes after it was queued.
     */
    public const QUEUED_ACTIVE_MINUTES = 30;

    /**
     * A started run counts as active for this many minutes after it started.
     */
    public const RUNNING_ACTIVE_MINUTES = 10;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'trigger' => ThreadsSearchRunTrigger::class,
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'queries_sent' => 'integer',
            'posts_stored' => 'integer',
            'outcome' => ThreadsSearchRunOutcome::class,
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
     * Runs that are queued or in progress and not yet considered abandoned.
     *
     * @param  Builder<ThreadsSearchRun>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('finished_at')->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->whereNull('started_at')
                    ->where('queued_at', '>', now()->subMinutes(self::QUEUED_ACTIVE_MINUTES));
            })->orWhere('started_at', '>', now()->subMinutes(self::RUNNING_ACTIVE_MINUTES));
        });
    }

    /**
     * Runs that never recorded their end and are no longer considered active.
     *
     * @param  Builder<ThreadsSearchRun>  $query
     */
    public function scopeStale(Builder $query): void
    {
        $query->whereNull('finished_at')->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->whereNull('started_at')
                    ->where('queued_at', '<=', now()->subMinutes(self::QUEUED_ACTIVE_MINUTES));
            })->orWhere('started_at', '<=', now()->subMinutes(self::RUNNING_ACTIVE_MINUTES));
        });
    }

    /**
     * The state of an active run: `queued` until it starts, `running` afterwards.
     */
    public function isQueued(): bool
    {
        return $this->started_at === null;
    }
}
