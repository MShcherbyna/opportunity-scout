<?php

namespace App\Http\Resources;

use App\Models\ThreadsConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThreadsConnection
 */
class ThreadsConnectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'username' => $this->username,
            'name' => $this->name,
            'status' => $this->status->value,
            'disconnectReason' => $this->disconnect_reason?->value,
            'connectedAt' => $this->connected_at->toIso8601String(),
            'tokenExpiresAt' => $this->token_expires_at?->toIso8601String(),
        ];
    }
}
