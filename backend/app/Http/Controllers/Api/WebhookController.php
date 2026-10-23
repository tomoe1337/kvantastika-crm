<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class WebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $secret = (string) config('services.webhook.secret', '');
        $provided = (string) $request->header('X-Webhook-Token', '');

        if ($secret === '' || ! hash_equals($secret, $provided)) {
            return response()->json(['message' => 'Invalid webhook token.'], 401);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:255'],
            'event_time' => ['required', 'date'],
            'deal' => ['required', 'array'],
            'deal.id' => ['required', 'string', 'max:255'],
            'deal.version' => ['required', 'integer', 'min:1'],
            'deal.updated_at' => ['required', 'date'],
            'deal.title' => ['required', 'string', 'max:255'],
            'deal.status' => ['required', 'string', 'max:32'],
            'deal.amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $validated['event_id'] = trim($validated['event_id']);
        $validated['deal']['id'] = trim($validated['deal']['id']);
        $validated['deal']['title'] = trim($validated['deal']['title']);
        $validated['deal']['status'] = trim($validated['deal']['status']);

        try {
            $event = DB::transaction(fn (): WebhookEvent => WebhookEvent::query()->create([
                'event_id' => $validated['event_id'],
                'event_time' => $validated['event_time'],
                'external_id' => $validated['deal']['id'],
                'payload' => $validated,
                'status' => 'received',
            ]));
        } catch (QueryException $exception) {
            if (WebhookEvent::query()->where('event_id', $validated['event_id'])->exists()) {
                return response()->json([
                    'status' => 'duplicate',
                    'duplicate' => true,
                    'event_id' => $validated['event_id'],
                ]);
            }

            throw $exception;
        }

        try {
            ProcessWebhookEvent::dispatch($event->id);
        } catch (Throwable $exception) {
            // The durable event remains in `received` and can be requeued by the recovery command.
            $event->forceFill(['last_error' => 'Queue dispatch failed.'])->save();
        }

        return response()->json([
            'status' => 'received',
            'duplicate' => false,
            'event_id' => $event->event_id,
        ], 202);
    }
}
