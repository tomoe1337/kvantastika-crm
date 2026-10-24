<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MockApiRateLimiter
{
    public function hit(string $externalId): int
    {
        $key = $this->key($externalId);
        Cache::add($key, 0, now()->addDay());

        return (int) Cache::increment($key);
    }

    public function reset(string $externalId): void
    {
        Cache::forget($this->key($externalId));
    }

    private function key(string $externalId): string
    {
        return 'mock-api:rate-limit:'.sha1($externalId);
    }
}
