<?php

namespace App\Models;

use App\Enums\ThreadsDataDeletionStatus;
use Database\Factories\ThreadsDataDeletionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $confirmation_code
 * @property ThreadsDataDeletionStatus $status
 * @property Carbon $requested_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['confirmation_code', 'status', 'requested_at', 'completed_at'])]
class ThreadsDataDeletionRequest extends Model
{
    /** @use HasFactory<ThreadsDataDeletionRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ThreadsDataDeletionStatus::class,
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
