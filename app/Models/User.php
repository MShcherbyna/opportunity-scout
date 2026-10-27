<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\SearchInterval;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int $search_interval_minutes
 * @property int $first_search_lookback_days
 * @property bool $manual_only
 * @property string|null $reply_template
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'search_interval_minutes', 'first_search_lookback_days', 'manual_only', 'reply_template'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'search_interval_minutes' => 'integer',
            'first_search_lookback_days' => 'integer',
            'manual_only' => 'boolean',
        ];
    }

    /**
     * The user's search interval, falling back to the default when the stored value is unknown.
     */
    public function searchInterval(): SearchInterval
    {
        return SearchInterval::tryFrom((int) $this->search_interval_minutes) ?? SearchInterval::Fifteen;
    }

    /**
     * @return HasOne<ThreadsConnection, $this>
     */
    public function threadsConnection(): HasOne
    {
        return $this->hasOne(ThreadsConnection::class);
    }

    /**
     * @return HasMany<ThreadsKeyword, $this>
     */
    public function threadsKeywords(): HasMany
    {
        return $this->hasMany(ThreadsKeyword::class);
    }

    /**
     * @return HasMany<ThreadsPost, $this>
     */
    public function threadsPosts(): HasMany
    {
        return $this->hasMany(ThreadsPost::class);
    }

    /**
     * @return HasMany<ThreadsSearchRun, $this>
     */
    public function threadsSearchRuns(): HasMany
    {
        return $this->hasMany(ThreadsSearchRun::class);
    }
}
