<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartDealSyncRequest;
use App\Models\Deal;
use App\Services\StartDealSyncService;
use Illuminate\Http\JsonResponse;

class RefreshDealController extends Controller
{
    public function __construct(private readonly StartDealSyncService $sync) {}

    public function store(StartDealSyncRequest $request, Deal $deal): JsonResponse
    {
        $result = $this->sync->start($deal, $request->validated('mode') ?? 'success');

        return response()->json([
            'operation' => $result['operation'],
            'already_running' => $result['created'] === false,
        ], $result['created'] ? 202 : 200);
    }
}
