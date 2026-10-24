<?php

use App\Jobs\ProcessWebhookEvent;
use App\Jobs\SyncDealFromExternalCrm;
use App\Models\SyncOperation;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('operations:requeue-pending {--stale-minutes=5}', function (): int {
    $cutoff = Carbon::now()->subMinutes(max(1, (int) $this->option('stale-minutes')));
    $webhooks = 0;
    $syncOperations = 0;

    WebhookEvent::query()
        ->where(function ($query) use ($cutoff): void {
            $query->where('status', 'received')
                ->orWhere(function ($stale) use ($cutoff): void {
                    $stale->where('status', 'processing')->where('updated_at', '<', $cutoff);
                });
        })
        ->orderBy('id')
        ->chunkById(100, function ($events) use (&$webhooks): void {
            foreach ($events as $event) {
                if ($event->status === 'processing') {
                    $event->forceFill(['status' => 'received'])->save();
                }

                ProcessWebhookEvent::dispatch($event->id);
                $webhooks++;
            }
        });

    SyncOperation::query()
        ->where(function ($query) use ($cutoff): void {
            $query->where('status', 'queued')
                ->orWhere(function ($stale) use ($cutoff): void {
                    $stale->where('status', 'running')->where('updated_at', '<', $cutoff);
                });
        })
        ->orderBy('id')
        ->chunkById(100, function ($operations) use (&$syncOperations): void {
            foreach ($operations as $operation) {
                if ($operation->status === 'running') {
                    $operation->forceFill(['status' => 'queued'])->save();
                }

                SyncDealFromExternalCrm::dispatch($operation->id);
                $syncOperations++;
            }
        });

    $this->info("Requeued {$webhooks} webhook event(s) and {$syncOperations} sync operation(s).");

    return self::SUCCESS;
})->purpose('Requeue durable webhook events and synchronization operations');
