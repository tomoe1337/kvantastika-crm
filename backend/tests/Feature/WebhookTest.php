<?php

namespace Tests\Feature;

use App\Models\Deal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.webhook.secret', 'test-webhook-secret');
    }

    public function test_duplicate_event_id_is_saved_and_applied_once(): void
    {
        $payload = $this->payload('evt-duplicate', 4, 'Course');

        $this->postJson('/api/webhooks/deals', $payload, $this->token())->assertAccepted();
        $this->postJson('/api/webhooks/deals', $payload, $this->token())
            ->assertOk()
            ->assertJsonPath('status', 'duplicate');

        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseCount('deals', 1);
        $this->assertDatabaseHas('deals', [
            'external_id' => 'deal-1',
            'external_version' => 4,
            'title' => 'Course',
        ]);
    }

    public function test_older_webhook_is_recorded_but_does_not_overwrite_newer_deal(): void
    {
        Deal::query()->create([
            'external_id' => 'deal-1',
            'title' => 'Latest title',
            'status' => 'WON',
            'amount' => '500.00',
            'external_version' => 7,
        ]);

        $this->postJson('/api/webhooks/deals', $this->payload('evt-old', 6, 'Stale title'), $this->token())
            ->assertAccepted();

        $this->assertDatabaseHas('deals', [
            'external_id' => 'deal-1',
            'title' => 'Latest title',
            'external_version' => 7,
        ]);
        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evt-old',
            'status' => 'ignored',
            'outcome' => 'stale_version',
        ]);
    }

    public function test_equal_version_with_different_values_preserves_local_edits(): void
    {
        Deal::query()->create([
            'external_id' => 'deal-1',
            'title' => 'Local title',
            'status' => 'WON',
            'amount' => '500.00',
            'external_version' => 7,
        ]);

        $this->postJson('/api/webhooks/deals', $this->payload('evt-conflict', 7, 'External title'), $this->token())
            ->assertAccepted();

        $this->assertDatabaseHas('deals', [
            'external_id' => 'deal-1',
            'title' => 'Local title',
            'external_version' => 7,
        ]);
        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evt-conflict',
            'status' => 'ignored',
            'outcome' => 'same_version_conflict',
        ]);
    }

    public function test_webhook_rejects_missing_or_invalid_secret_and_payload(): void
    {
        $this->postJson('/api/webhooks/deals', $this->payload('evt-invalid', 1, 'Title'))
            ->assertUnauthorized();

        $this->postJson('/api/webhooks/deals', ['event_id' => 'evt-invalid'], $this->token())
            ->assertUnprocessable();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_deal_detail_returns_webhook_status_without_raw_payload(): void
    {
        $this->postJson('/api/webhooks/deals', $this->payload('evt-resource', 2, 'Resource title'), $this->token())
            ->assertAccepted();

        $deal = Deal::query()->where('external_id', 'deal-1')->firstOrFail();

        $this->getJson("/api/deals/{$deal->id}")
            ->assertOk()
            ->assertJsonPath('deal.external_id', 'deal-1')
            ->assertJsonPath('webhook_events.0.event_id', 'evt-resource')
            ->assertJsonPath('webhook_events.0.status', 'applied')
            ->assertJsonMissingPath('webhook_events.0.payload')
            ->assertJsonMissingPath('webhook_events.0.external_id');
    }

    private function payload(string $eventId, int $version, string $title): array
    {
        return [
            'event_id' => $eventId,
            'event_time' => '2026-10-15T15:30:00Z',
            'deal' => [
                'id' => 'deal-1',
                'version' => $version,
                'updated_at' => '2026-10-15T15:29:50Z',
                'title' => $title,
                'status' => 'NEW',
                'amount' => 49000,
            ],
        ];
    }

    private function token(): array
    {
        return ['X-Webhook-Token' => 'test-webhook-secret'];
    }
}
