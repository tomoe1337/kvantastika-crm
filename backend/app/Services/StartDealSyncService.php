<?php

namespace App\Services;

use App\Exceptions\DealWithoutExternalIdException;
use App\Jobs\SyncDealFromExternalCrm;
use App\Models\Deal;
use App\Models\SyncOperation;
use Illuminate\Support\Facades\DB;
use Throwable;

class StartDealSyncService
{
    /** @return array{operation: SyncOperation, created: bool} */
    public function start(Deal $deal, string $mode = 'success'): array
    {
        if ($deal->external_id === null) {
            throw new DealWithoutExternalIdException;
        }

        [$operation, $created] = DB::transaction(function () use ($deal, $mode): array {
            $lockedDeal = Deal::query()->lockForUpdate()->findOrFail($deal->id);
            $active = $lockedDeal->syncOperations()
                ->whereIn('status', ['queued', 'running'])
                ->latest()
                ->first();

            if ($active) {
                return [$active, false];
            }

            return [$lockedDeal->syncOperations()->create([
                'status' => 'queued',
                'mode' => $mode,
                'attempts' => 0,
                'max_attempts' => 3,
            ]), true];
        });

        if ($created) {
            try {
                SyncDealFromExternalCrm::dispatch($operation->id)->afterCommit();
            } catch (Throwable $exception) {
                $operation->forceFill([
                    'last_error' => 'Queue dispatch failed; it can be requeued.',
                ])->save();
            }
        }

        return ['operation' => $operation->refresh(), 'created' => $created];
    }
}
