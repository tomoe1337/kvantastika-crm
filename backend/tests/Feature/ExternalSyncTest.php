<?php

namespace Tests\Feature;

use App\Exceptions\RetryableExternalResponse;
use App\Jobs\SyncDealFromExternalCrm;
use App\Models\Deal;
use App\Models\SyncOperation;
use App\Services\ExternalDealSyncService;
use App\Services\MockApiRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.mock_api.url', 'http://mock.test');
    }

    public function test_two_rate_limits_then_success_updates_deal_on_third_attempt(): void
    {
        $deal = $this->makeDeal();
        $operation = $this->makeOperation($deal, 'rate_limit');

        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['message' => 'slow down'], 429)
            ->push(['message' => 'slow down'], 429)
            ->push([
                'id' => 'external-1',
                'version' => 2,
                'updated_at' => '2026-10-16T10:00:00Z',
                'title' => 'New external title',
                'status' => 'WON',
                'amount' => 30000,
            ], 200);

        $sync = app(ExternalDealSyncService::class);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $sync->run($operation->id);
                $this->fail('A 429 response must schedule a retry.');
            } catch (RetryableExternalResponse) {
                $this->assertDatabaseHas('sync_operations', [
                    'id' => $operation->id,
                    'status' => 'queued',
                    'attempts' => $attempt + 1,
                ]);
            }
        }

        $sync->run($operation->id);

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'title' => 'New external title',
            'external_version' => 2,
        ]);
        $this->assertDatabaseHas('sync_operations', [
            'id' => $operation->id,
            'status' => 'succeeded',
            'outcome' => 'applied',
            'attempts' => 3,
        ]);
        Http::assertSentCount(3);
    }

    public function test_permanent_server_errors_end_in_a_terminal_failure_after_three_attempts(): void
    {
        $deal = $this->makeDeal();
        $operation = $this->makeOperation($deal, 'server_error');

        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['message' => 'unavailable'], 500)
            ->push(['message' => 'unavailable'], 500)
            ->push(['message' => 'unavailable'], 500);

        $sync = app(ExternalDealSyncService::class);
        $lastException = null;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $sync->run($operation->id);
            } catch (RetryableExternalResponse $exception) {
                $lastException = $exception;
            }
        }

        (new SyncDealFromExternalCrm($operation->id))->failed($lastException);

        $this->assertDatabaseHas('sync_operations', [
            'id' => $operation->id,
            'status' => 'failed',
            'attempts' => 3,
        ]);
        Http::assertSentCount(3);
    }

    public function test_stale_get_response_is_successfully_ignored(): void
    {
        $deal = $this->makeDeal(version: 5);
        $operation = $this->makeOperation($deal, 'success');

        Http::fake([
            '*' => Http::response([
                'id' => 'external-1',
                'version' => 4,
                'updated_at' => '2026-10-14T10:00:00Z',
                'title' => 'Stale title',
                'status' => 'NEW',
                'amount' => 100,
            ], 200),
        ]);

        app(ExternalDealSyncService::class)->run($operation->id);

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'title' => 'Local title',
            'external_version' => 5,
        ]);
        $this->assertDatabaseHas('sync_operations', [
            'id' => $operation->id,
            'status' => 'succeeded',
            'outcome' => 'ignored_stale',
        ]);
    }

    public function test_same_version_get_conflict_preserves_local_edits(): void
    {
        $deal = $this->makeDeal(version: 5);
        $operation = $this->makeOperation($deal, 'success');

        Http::fake([
            '*' => Http::response([
                'id' => 'external-1',
                'version' => 5,
                'updated_at' => '2026-10-16T10:00:00Z',
                'title' => 'External title at same version',
                'status' => 'WON',
                'amount' => 52000,
            ], 200),
        ]);

        app(ExternalDealSyncService::class)->run($operation->id);

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'title' => 'Local title',
            'status' => 'NEW',
            'amount' => '49000.00',
            'external_version' => 5,
        ]);
        $this->assertDatabaseHas('sync_operations', [
            'id' => $operation->id,
            'status' => 'succeeded',
            'outcome' => 'conflict',
            'external_version' => 5,
        ]);
    }

    public function test_refresh_requires_an_external_id(): void
    {
        $deal = Deal::query()->create([
            'external_id' => null,
            'title' => 'Local-only deal',
            'status' => 'NEW',
            'amount' => '1000.00',
            'external_version' => 0,
        ]);

        $this->postJson("/api/deals/{$deal->id}/refresh")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This deal has no external CRM id.');
    }

    public function test_rate_limit_counter_resets_after_successful_operation(): void
    {
        $deal = $this->makeDeal();
        $operation = $this->makeOperation($deal, 'rate_limit');
        $limiter = app(MockApiRateLimiter::class);

        $this->assertSame(1, $limiter->hit('external-1'));
        $this->assertSame(2, $limiter->hit('external-1'));

        Http::fake([
            '*' => Http::response([
                'id' => 'external-1',
                'version' => 2,
                'updated_at' => '2026-10-16T10:00:00Z',
                'title' => 'New external title',
                'status' => 'WON',
                'amount' => 30000,
            ], 200),
        ]);

        app(ExternalDealSyncService::class)->run($operation->id);

        $this->assertDatabaseHas('sync_operations', [
            'id' => $operation->id,
            'status' => 'succeeded',
        ]);
        $this->assertSame(1, $limiter->hit('external-1'));
    }

    public function test_rate_limit_counter_resets_after_failed_operation(): void
    {
        $deal = $this->makeDeal();
        $operation = $this->makeOperation($deal, 'rate_limit');
        $limiter = app(MockApiRateLimiter::class);

        $this->assertSame(1, $limiter->hit('external-1'));
        $this->assertSame(2, $limiter->hit('external-1'));

        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['message' => 'unavailable'], 500)
            ->push(['message' => 'unavailable'], 500)
            ->push(['message' => 'unavailable'], 500);

        $sync = app(ExternalDealSyncService::class);
        $lastException = null;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $sync->run($operation->id);
            } catch (RetryableExternalResponse $exception) {
                $lastException = $exception;
            }
        }

        (new SyncDealFromExternalCrm($operation->id))->failed($lastException);

        $this->assertDatabaseHas('sync_operations', [
            'id' => $operation->id,
            'status' => 'failed',
        ]);
        $this->assertSame(1, $limiter->hit('external-1'));
    }

    private function makeDeal(int $version = 1): Deal
    {
        return Deal::query()->create([
            'external_id' => 'external-1',
            'title' => 'Local title',
            'status' => 'NEW',
            'amount' => '49000.00',
            'external_version' => $version,
        ]);
    }

    private function makeOperation(Deal $deal, string $mode): SyncOperation
    {
        return SyncOperation::query()->create([
            'deal_id' => $deal->id,
            'status' => 'queued',
            'mode' => $mode,
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
    }
}
