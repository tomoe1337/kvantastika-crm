<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveWebhookRequest;
use App\Services\WebhookIngestionService;
use Illuminate\Http\JsonResponse;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookIngestionService $webhooks) {}

    public function store(ReceiveWebhookRequest $request): JsonResponse
    {
        $result = $this->webhooks->ingest($request->validated());

        if ($result['duplicate']) {
            return response()->json([
                'status' => 'duplicate',
                'duplicate' => true,
                'event_id' => $result['event_id'],
            ]);
        }

        return response()->json([
            'status' => 'received',
            'duplicate' => false,
            'event_id' => $result['event_id'],
        ], 202);
    }
}
