<?php

namespace Tests\Feature;

use App\Models\MockDeal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MockApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limit_mode_returns_two_429_responses_then_a_stable_success(): void
    {
        MockDeal::query()->create([
            'external_id' => 'mock-1',
            'title' => 'Mock course',
            'status' => 'NEW',
            'amount' => '49000.00',
            'version' => 3,
            'external_updated_at' => '2026-10-15T15:29:50Z',
        ]);
        Cache::flush();

        $this->getJson('/mock-api/deals/mock-1?mode=rate_limit')->assertStatus(429);
        $this->getJson('/mock-api/deals/mock-1?mode=rate_limit')->assertStatus(429);
        $this->getJson('/mock-api/deals/mock-1?mode=rate_limit')
            ->assertOk()
            ->assertJsonPath('version', 3)
            ->assertJsonPath('id', 'mock-1');
        $this->getJson('/mock-api/deals/mock-1?mode=rate_limit')->assertOk();
    }

    public function test_success_fixture_can_be_updated_and_rate_limit_counter_reset(): void
    {
        $this->putJson('/mock-api/deals/mock-new', [
            'version' => 1,
            'updated_at' => '2026-10-15T15:29:50Z',
            'title' => 'Created fixture',
            'status' => 'NEW',
            'amount' => 1200,
        ])->assertOk()->assertJsonPath('id', 'mock-new');

        $this->postJson('/mock-api/deals/mock-new/reset')->assertOk();
        $this->getJson('/mock-api/deals/mock-new?mode=server_error')->assertStatus(500);
        $this->getJson('/mock-api/deals/not-present')->assertNotFound();
    }
}
