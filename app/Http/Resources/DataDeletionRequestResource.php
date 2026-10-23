<?php

namespace App\Http\Resources;

use App\Models\ThreadsDataDeletionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThreadsDataDeletionRequest
 */
class DataDeletionRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'confirmationCode' => $this->confirmation_code,
            'status' => $this->status->value,
            'requestedAt' => $this->requested_at->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
        ];
    }
}
