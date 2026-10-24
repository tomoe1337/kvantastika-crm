<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncDealFromExternalCrm;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefreshDealController extends Controller
{
    public function store(Request $request, Deal $deal): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'in:success,rate_limit,server_error'],
        ]);

        if ($deal->external_id === null) {
            return response()->json(['message' => 'This deal has no external CRM id.'], 422);
        }

        [$operation, $created] = DB::transaction(function () use ($deal, $validated): array {
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
                'mode' => $validated['mode'] ?? 'success',
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

        $operation->refresh();

        return response()->json([
            'operation' => $operation,
            'already_running' => ! $created,
        ], $created ? 202 : 200);
    }
}
