<?php

namespace App\Jobs;

use App\Models\SyncOperation;
use App\Services\ExternalDealSyncService;
use App\Services\MockApiRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncDealFromExternalCrm implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $operationId) {}

    public function backoff(): array
    {
        return [1, 2];
    }

    public function handle(ExternalDealSyncService $sync): void
    {
        $sync->run($this->operationId);
    }

    public function failed(?Throwable $exception): void
    {
        $operation = SyncOperation::query()->find($this->operationId);

        if ($operation === null || in_array($operation->status, ['succeeded', 'failed'], true)) {
            return;
        }

        $operation->forceFill([
            'status' => 'failed',
            'outcome' => 'failed',
            'attempts' => max($operation->attempts, $operation->max_attempts),
            'last_error' => $exception
                ? mb_substr($exception::class.': '.$exception->getMessage(), 0, 2000)
                : 'External CRM synchronization failed.',
            'finished_at' => now(),
        ])->save();

        $externalId = $operation->deal?->external_id;

        if ($operation->mode === 'rate_limit' && $externalId !== null) {
            app(MockApiRateLimiter::class)->reset($externalId);
        }
    }
}
