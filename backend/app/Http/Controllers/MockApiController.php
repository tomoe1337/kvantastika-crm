<?php

namespace App\Http\Controllers;

use App\Http\Requests\MockDealRequest;
use App\Http\Requests\UpdateMockDealRequest;
use App\Http\Resources\MockDealResource;
use App\Services\MockCrmService;
use Illuminate\Http\JsonResponse;

class MockApiController extends Controller
{
    public function __construct(private readonly MockCrmService $mockCrm) {}

    public function show(MockDealRequest $request, string $externalId): JsonResponse
    {
        $result = $this->mockCrm->read($externalId, $request->validated('mode') ?? 'success');
        $body = $result['deal'] !== null ? new MockDealResource($result['deal']) : $result['body'];

        return response()->json($body, $result['status'], $result['headers']);
    }

    public function store(UpdateMockDealRequest $request, string $externalId): JsonResponse
    {
        $deal = $this->mockCrm->update($externalId, $request->validated());

        return response()->json(new MockDealResource($deal));
    }

    public function reset(string $externalId): JsonResponse
    {
        $this->mockCrm->reset($externalId);

        return response()->json(['status' => 'reset', 'id' => $externalId]);
    }
}
