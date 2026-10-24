<?php

namespace App\Services;

use App\Models\MockDeal;

class MockCrmService
{
    public function __construct(private readonly MockApiRateLimiter $rateLimiter) {}

    /** @return array{status: int, body: array, headers: array<string, string>} */
    public function read(string $externalId, string $mode): array
    {
        if ($mode === 'server_error') {
            return [
                'status' => 500,
                'body' => ['message' => 'Mock CRM is temporarily unavailable.'],
                'headers' => [],
            ];
        }

        if ($mode === 'rate_limit') {
            $attempt = $this->rateLimiter->hit($externalId);

            if ($attempt <= 2) {
                return [
                    'status' => 429,
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
                'body' => ['message' => 'Mock deal not found.'],
                'headers' => [],
            ];
        }

        return ['status' => 200, 'body' => $this->serialize($deal), 'headers' => []];
    }

    public function update(string $externalId, array $data): array
    {
        $deal = MockDeal::query()->updateOrCreate(
            ['external_id' => $externalId],
            [
                'version' => $data['version'],
                'external_updated_at' => $data['updated_at'],
                'title' => trim($data['title']),
                'status' => trim($data['status']),
                'amount' => $data['amount'],
            ],
        );

        return $this->serialize($deal);
    }

    public function reset(string $externalId): void
    {
        $this->rateLimiter->reset($externalId);
    }

    private function serialize(MockDeal $deal): array
    {
        return [
            'id' => $deal->external_id,
            'version' => $deal->version,
            'updated_at' => $deal->external_updated_at?->toISOString(),
            'title' => $deal->title,
            'status' => $deal->status,
            'amount' => (float) $deal->amount,
        ];
    }
}
