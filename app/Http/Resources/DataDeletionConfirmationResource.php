<?php

namespace App\Http\Resources;

use App\Models\ThreadsDataDeletionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThreadsDataDeletionRequest
 */
class DataDeletionConfirmationResource extends JsonResource
{
    /**
     * Transform the resource into the payload Meta expects from the data deletion callback.
     *
     * @return array{url: string, confirmation_code: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => url('/data-deletion').'?code='.$this->confirmation_code,
            'confirmation_code' => $this->confirmation_code,
        ];
    }
}
