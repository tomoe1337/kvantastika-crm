<?php

namespace App\Http\Controllers;

use App\Http\Requests\MockDealRequest;
use App\Http\Requests\UpdateMockDealRequest;
use App\Services\MockCrmService;
use Illuminate\Http\JsonResponse;

class MockApiController extends Controller
{
    public function __construct(private readonly MockCrmService $mockCrm) {}

    public function show(MockDealRequest $request, string $externalId): JsonResponse
    {
        $result = $this->mockCrm->read($externalId, $request->validated('mode') ?? 'success');

        return response()->json($result['body'], $result['status'], $result['headers']);
    }

    public function store(UpdateMockDealRequest $request, string $externalId): JsonResponse
    {
        return response()->json($this->mockCrm->update($externalId, $request->validated()));
    }

    public function reset(string $externalId): JsonResponse
    {
        $this->mockCrm->reset($externalId);

        return response()->json(['status' => 'reset', 'id' => $externalId]);
    }
}
