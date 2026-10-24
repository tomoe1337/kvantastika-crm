<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'status' => $this->status,
            'amount' => $this->amount,
            'external_updated_at' => $this->external_updated_at?->toISOString(),
            'external_version' => $this->external_version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'latest_sync_operation' => $this->whenLoaded(
                'latestSyncOperation',
                fn ($operation) => $operation ? new SyncOperationResource($operation) : null,
            ),
        ];
    }
}
