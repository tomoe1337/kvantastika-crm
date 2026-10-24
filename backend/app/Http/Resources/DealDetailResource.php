<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'deal' => new DealResource($this->resource['deal']),
            'sync_operations' => SyncOperationResource::collection($this->resource['sync_operations']),
            'webhook_events' => WebhookEventResource::collection($this->resource['webhook_events']),
        ];
    }
}
