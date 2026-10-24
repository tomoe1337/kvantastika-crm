<?php

namespace App\Http\Controllers;

use App\Models\MockDeal;
use App\Services\MockApiRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockApiController extends Controller
{
    public function __construct(private readonly MockApiRateLimiter $rateLimiter) {}

    public function show(Request $request, string $externalId): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'in:success,rate_limit,server_error'],
        ]);
        $mode = $validated['mode'] ?? 'success';

        if ($mode === 'server_error') {
            return response()->json(['message' => 'Mock CRM is temporarily unavailable.'], 500);
        }

        if ($mode === 'rate_limit') {
            $attempt = $this->rateLimiter->hit($externalId);

            if ($attempt <= 2) {
                return response()->json([
                    'message' => 'Mock CRM rate limit. Retry the request.',
                    'attempt' => $attempt,
                ], 429, ['Retry-After' => '1']);
            }
        }

        $deal = MockDeal::query()->where('external_id', $externalId)->first();

        if (! $deal) {
            return response()->json(['message' => 'Mock deal not found.'], 404);
        }

        return response()->json([
            'id' => $deal->external_id,
            'version' => $deal->version,
            'updated_at' => $deal->external_updated_at?->toISOString(),
            'title' => $deal->title,
            'status' => $deal->status,
            'amount' => (float) $deal->amount,
        ]);
    }

    public function store(Request $request, string $externalId): JsonResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'updated_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $deal = MockDeal::query()->updateOrCreate(
            ['external_id' => $externalId],
            [
                'version' => $validated['version'],
                'external_updated_at' => $validated['updated_at'],
                'title' => trim($validated['title']),
                'status' => trim($validated['status']),
                'amount' => $validated['amount'],
            ],
        );

        return response()->json([
            'id' => $deal->external_id,
            'version' => $deal->version,
            'updated_at' => $deal->external_updated_at?->toISOString(),
            'title' => $deal->title,
            'status' => $deal->status,
            'amount' => (float) $deal->amount,
        ]);
    }

    public function reset(string $externalId): JsonResponse
    {
        $this->rateLimiter->reset($externalId);

        return response()->json(['status' => 'reset', 'id' => $externalId]);
    }
}
