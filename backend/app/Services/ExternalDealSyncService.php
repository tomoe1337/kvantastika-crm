<?php

namespace App\Services;

use App\Exceptions\RetryableExternalResponse;
use App\Models\SyncOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ExternalDealSyncService
{
    public function __construct(
        private readonly ApplyExternalDealState $applier,
        private readonly MockApiRateLimiter $rateLimiter,
    ) {}

    public function run(int $operationId): void
    {
        $operation = DB::transaction(function () use ($operationId): ?SyncOperation {
            $operation = SyncOperation::query()->lockForUpdate()->find($operationId);

            if (! $operation || in_array($operation->status, ['succeeded', 'failed'], true)) {
                return null;
            }

            if ($operation->status === 'running' && $operation->updated_at->gt(now()->subMinutes(5))) {
                return null;
            }

            $operation->forceFill([
                'status' => 'running',
                'attempts' => $operation->attempts + 1,
                'started_at' => $operation->started_at ?? now(),
                'last_error' => null,
            ])->save();

            return $operation->load('deal');
        });

        if ($operation === null || $operation->deal?->external_id === null) {
            return;
        }

        try {
            $baseUrl = rtrim((string) config('services.mock_api.url'), '/');
            $url = $baseUrl.'/mock-api/deals/'.rawurlencode($operation->deal->external_id);
            $response = Http::acceptJson()->timeout(5)->get($url, ['mode' => $operation->mode]);

            if ($response->status() === 429 || $response->serverError()) {
                $message = 'Mock CRM returned HTTP '.$response->status();

                $operation->forceFill([
                    'status' => 'queued',
                    'last_error' => $message,
                ])->save();

                if ($operation->attempts >= $operation->max_attempts) {
                    throw new RetryableExternalResponse($message.' after final attempt.');
                }

                throw new RetryableExternalResponse($message);
            }

            if (! $response->successful()) {
                $this->finishFailure($operation, 'Mock CRM returned HTTP '.$response->status());

                return;
            }

            $external = $response->json();
            $validator = Validator::make(is_array($external) ? $external : [], [
                'id' => ['required', 'string', 'max:255'],
                'version' => ['required', 'integer', 'min:1'],
                'updated_at' => ['required', 'date'],
                'title' => ['required', 'string', 'max:255'],
                'status' => ['required', 'string', 'max:32'],
                'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            ]);

            if ($validator->fails()) {
                $this->finishFailure($operation, 'Mock CRM returned an invalid deal payload.');

                return;
            }

            if ((string) $external['id'] !== (string) $operation->deal->external_id) {
                $this->finishFailure($operation, 'Mock CRM returned a different deal id.');

                return;
            }

            $result = $this->applier->apply($external);
            $outcome = match ($result['outcome']) {
                'applied' => 'applied',
                'ignored_stale' => 'ignored_stale',
                'same_version_conflict' => 'conflict',
                default => 'unchanged',
            };

            $operation->forceFill([
                'status' => 'succeeded',
                'outcome' => $outcome,
                'external_version' => (int) $external['version'],
                'finished_at' => now(),
                'last_error' => null,
            ])->save();

            $this->resetRateLimit($operation);
        } catch (RetryableExternalResponse $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->finishFailure($operation, mb_substr($exception::class.': '.$exception->getMessage(), 0, 2000));
        }
    }

    private function finishFailure(SyncOperation $operation, string $message): void
    {
        $operation->forceFill([
            'status' => 'failed',
            'outcome' => 'failed',
            'last_error' => $message,
            'finished_at' => now(),
        ])->save();

        $this->resetRateLimit($operation);
    }

    private function resetRateLimit(SyncOperation $operation): void
    {
        $externalId = $operation->deal?->external_id;

        if ($operation->mode === 'rate_limit' && $externalId !== null) {
            $this->rateLimiter->reset($externalId);
        }
    }
}
