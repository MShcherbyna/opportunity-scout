<?php

namespace App\Http\Resources;

use App\Models\ThreadsConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThreadsConnection
 */
class ThreadsConnectionSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{status: string, disconnectReason: string|null, username: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'disconnectReason' => $this->disconnect_reason?->value,
            'username' => $this->username,
        ];
    }
}
