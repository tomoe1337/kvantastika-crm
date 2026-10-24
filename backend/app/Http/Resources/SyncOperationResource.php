<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncOperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deal_id' => $this->deal_id,
            'status' => $this->status,
            'mode' => $this->mode,
            'outcome' => $this->outcome,
            'attempts' => $this->attempts,
            'max_attempts' => $this->max_attempts,
            'external_version' => $this->external_version,
            'last_error' => $this->last_error,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
