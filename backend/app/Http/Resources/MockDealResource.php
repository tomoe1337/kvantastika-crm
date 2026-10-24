<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MockDealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->external_id,
            'version' => $this->version,
            'updated_at' => $this->external_updated_at?->toISOString(),
            'title' => $this->title,
            'status' => $this->status,
            'amount' => (float) $this->amount,
        ];
    }
}
