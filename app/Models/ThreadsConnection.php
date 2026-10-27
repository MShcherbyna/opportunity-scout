<?php

namespace App\Models;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDisconnectReason;
use Carbon\CarbonInterface;
use Database\Factories\ThreadsConnectionFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $user_id
 * @property string $threads_user_id
 * @property string $username
 * @property string|null $name
 * @property string|null $access_token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $token_issued_at
 * @property ThreadsConnectionStatus $status
 * @property ThreadsDisconnectReason|null $disconnect_reason
 * @property Carbon|null $search_paused_until
 * @property Carbon $connected_at
 * @property Carbon|null $disconnected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'threads_user_id',
    'username',
    'name',
    'access_token',
    'token_expires_at',
    'token_issued_at',
    'status',
    'disconnect_reason',
    'search_paused_until',
    'connected_at',
    'disconnected_at',
])]
#[Hidden(['access_token'])]
class ThreadsConnection extends Model
{
    /** @use HasFactory<ThreadsConnectionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'token_issued_at' => 'datetime',
            'status' => ThreadsConnectionStatus::class,
            'disconnect_reason' => ThreadsDisconnectReason::class,
            'search_paused_until' => 'datetime',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
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
     * The attributes that make a connection disconnected: the token is deleted and searching stops.
     *
     * @return array<string, mixed>
     */
    public static function disconnectedAttributes(ThreadsDisconnectReason $reason): array
    {
        return [
            'access_token' => null,
            'status' => ThreadsConnectionStatus::Disconnected,
            'disconnect_reason' => $reason,
            'disconnected_at' => now(),
            'search_paused_until' => null,
        ];
    }

    /**
     * Mark the connection as disconnected: the token is deleted and searching stops.
     */
    public function markDisconnected(ThreadsDisconnectReason $reason): void
    {
        $this->forceFill(self::disconnectedAttributes($reason))->save();
    }

    /**
     * Disconnect only if the row is still connected with the token version this model was loaded with,
     * so a stale model cannot wipe a token that was refreshed or reconnected meanwhile.
     */
    public function markDisconnectedIfUnchanged(ThreadsDisconnectReason $reason): bool
    {
        $changed = $this->unchangedTokenQuery()->update(self::disconnectedAttributes($reason)) > 0;

        if ($changed) {
            $this->refresh();
        }

        return $changed;
    }

    /**
     * Whether the row is still the same connection this model was loaded with (connected, same connected_at).
     * A token refresh keeps it true; a disconnect or reconnect makes it false.
     * Uses a top-level shared-lock read, so inside a transaction it blocks a concurrent disconnect until commit (MySQL only; SQLite ignores locks).
     *
     * @phpstan-impure
     */
    public function isStillSameConnection(): bool
    {
        return self::query()
            ->whereKey($this->getKey())
            ->where('status', ThreadsConnectionStatus::Connected)
            ->where('connected_at', $this->connected_at)
            ->sharedLock()
            ->first(['id']) !== null;
    }

    /**
     * Store a refreshed token only if the row is still connected with the token version this model was loaded with.
     */
    public function storeRefreshedTokenIfUnchanged(string $accessToken, int $expiresInSeconds): bool
    {
        $issuedAt = now();

        $changed = $this->unchangedTokenQuery()->update([
            'access_token' => Crypt::encryptString($accessToken),
            'token_issued_at' => $issuedAt,
            'token_expires_at' => $issuedAt->copy()->addSeconds($expiresInSeconds),
        ]) > 0;

        if ($changed) {
            $this->refresh();
        }

        return $changed;
    }

    /**
     * Pause searching only while the row is still connected.
     */
    public function pauseSearchIfConnected(CarbonInterface $until): void
    {
        self::query()
            ->whereKey($this->getKey())
            ->where('status', ThreadsConnectionStatus::Connected)
            ->update(['search_paused_until' => $until]);
    }

    /**
     * @return Builder<ThreadsConnection>
     */
    private function unchangedTokenQuery(): Builder
    {
        $query = self::query()
            ->whereKey($this->getKey())
            ->where('status', ThreadsConnectionStatus::Connected);

        return $this->token_issued_at === null
            ? $query->whereNull('token_issued_at')
            : $query->where('token_issued_at', $this->token_issued_at);
    }

    /**
     * The decrypted access token, or null when there is none or it cannot be decrypted
     * (the connection is then disconnected with reason token_invalid).
     */
    public function usableAccessToken(): ?string
    {
        try {
            $token = $this->getAttribute('access_token');
        } catch (DecryptException) {
            $this->markDisconnectedIfUnchanged(ThreadsDisconnectReason::TokenInvalid);

            return null;
        }

        return filled($token) ? $token : null;
    }
}
