<?php

namespace App\Services;

use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class WebhookIngestionService
{
    /** @return array{event_id: string, duplicate: bool} */
    public function ingest(array $payload): array
    {
        $normalized = $this->normalize($payload);

        try {
            $event = DB::transaction(fn (): WebhookEvent => WebhookEvent::query()->create([
                'event_id' => $normalized['event_id'],
                'event_time' => $normalized['event_time'],
                'external_id' => $normalized['deal']['id'],
                'payload' => $normalized,
                'status' => 'received',
            ]));
        } catch (QueryException $exception) {
            if (WebhookEvent::query()->where('event_id', $normalized['event_id'])->exists()) {
                return ['event_id' => $normalized['event_id'], 'duplicate' => true];
            }

            throw $exception;
        }

        try {
            ProcessWebhookEvent::dispatch($event->id);
        } catch (Throwable $exception) {
            // The durable event remains in `received` and can be requeued by the recovery command.
            $event->forceFill(['last_error' => 'Queue dispatch failed.'])->save();
        }

        return ['event_id' => $event->event_id, 'duplicate' => false];
    }

    private function normalize(array $payload): array
    {
        $payload['event_id'] = trim($payload['event_id']);
        $payload['deal']['id'] = trim($payload['deal']['id']);
        $payload['deal']['title'] = trim($payload['deal']['title']);
        $payload['deal']['status'] = trim($payload['deal']['status']);

        return $payload;
    }
}
