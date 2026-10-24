<?php

namespace App\Services;

use App\Models\MockDeal;

class MockCrmService
{
    public function __construct(private readonly MockApiRateLimiter $rateLimiter) {}

    /** @return array{status: int, deal: MockDeal|null, body: array, headers: array<string, string>} */
    public function read(string $externalId, string $mode): array
    {
        if ($mode === 'server_error') {
            return [
                'status' => 500,
                'deal' => null,
                'body' => ['message' => 'Mock CRM is temporarily unavailable.'],
                'headers' => [],
            ];
        }

        if ($mode === 'rate_limit') {
            $attempt = $this->rateLimiter->hit($externalId);

            if ($attempt <= 2) {
                return [
                    'status' => 429,
                    'deal' => null,
                    'body' => [
                        'message' => 'Mock CRM rate limit. Retry the request.',
                        'attempt' => $attempt,
                    ],
                    'headers' => ['Retry-After' => '1'],
                ];
            }
        }

        $deal = MockDeal::query()->where('external_id', $externalId)->first();

        if ($deal === null) {
            return [
                'status' => 404,
                'deal' => null,
                'body' => ['message' => 'Mock deal not found.'],
                'headers' => [],
            ];
        }

        return ['status' => 200, 'deal' => $deal, 'body' => [], 'headers' => []];
    }

    public function update(string $externalId, array $data): MockDeal
    {
        return MockDeal::query()->updateOrCreate(
            ['external_id' => $externalId],
            [
                'version' => $data['version'],
                'external_updated_at' => $data['updated_at'],
                'title' => trim($data['title']),
                'status' => trim($data['status']),
                'amount' => $data['amount'],
            ],
        );
    }

    public function reset(string $externalId): void
    {
        $this->rateLimiter->reset($externalId);
    }
}
