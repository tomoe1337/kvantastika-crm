<?php

namespace Tests\Feature;

use App\Models\Deal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_deals_can_be_created_updated_searched_filtered_and_paginated(): void
    {
        $first = $this->postJson('/api/deals', [
            'external_id' => 'external-101',
            'title' => '  Intro Course  ',
            'status' => 'NEW',
            'amount' => 12500,
        ])->assertCreated()
            ->assertJsonPath('external_version', 0)
            ->assertJsonPath('title', 'Intro Course')
            ->json();

        $second = Deal::query()->create([
            'external_id' => 'external-202',
            'title' => 'Advanced Course',
            'status' => 'WON',
            'amount' => 30000,
            'external_version' => 4,
        ]);

        $this->patchJson('/api/deals/'.$first['id'], [
            'title' => 'Updated Course',
            'status' => 'IN_PROGRESS',
            'amount' => 13000,
        ])->assertOk()
            ->assertJsonPath('external_version', 0)
            ->assertJsonPath('status', 'IN_PROGRESS');

        $this->getJson('/api/deals?q=updated&status=IN_PROGRESS&per_page=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $first['id']);

        $this->getJson('/api/deals?q=external&per_page=1')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('per_page', 1);

        $this->assertDatabaseHas('deals', [
            'id' => $second->id,
            'external_version' => 4,
        ]);
    }
}
