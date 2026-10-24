<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\ApplyExternalDealState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $eventId) {}

    public function backoff(): array
    {
        return [1, 2];
    }

    public function handle(ApplyExternalDealState $applier): void
    {
        $event = DB::transaction(function (): ?WebhookEvent {
            $event = WebhookEvent::query()->lockForUpdate()->find($this->eventId);

            if ($event === null || in_array($event->status, ['applied', 'ignored', 'failed'], true)) {
                return null;
            }

            if ($event->status === 'processing' && $event->updated_at->gt(now()->subMinutes(5))) {
                return null;
            }

            $event->forceFill([
                'status' => 'processing',
                'attempts' => $event->attempts + 1,
            ])->save();

            return $event;
        });

        if ($event === null) {
            return;
        }

        try {
            $result = $applier->apply($event->payload['deal']);
            $outcome = match ($result['outcome']) {
                'applied' => 'applied',
                'ignored_stale' => 'stale_version',
                'same_version_conflict' => 'same_version_conflict',
                default => 'duplicate_version',
            };

            DB::transaction(function () use ($event, $outcome): void {
                $current = WebhookEvent::query()->lockForUpdate()->find($event->id);

                if ($current === null || $current->status === 'failed') {
                    return;
                }

                $current->forceFill([
                    'status' => $outcome === 'applied' ? 'applied' : 'ignored',
                    'outcome' => $outcome,
                    'last_error' => null,
                    'processed_at' => now(),
                ])->save();
            });
        } catch (Throwable $exception) {
            WebhookEvent::query()->whereKey($event->id)->update([
                'status' => 'received',
                'last_error' => mb_substr($exception::class.': '.$exception->getMessage(), 0, 2000),
                'updated_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        WebhookEvent::query()->whereKey($this->eventId)
            ->whereNotIn('status', ['applied', 'ignored'])
            ->update([
                'status' => 'failed',
                'last_error' => $exception
                    ? mb_substr($exception::class.': '.$exception->getMessage(), 0, 2000)
                    : 'Webhook processing failed.',
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
