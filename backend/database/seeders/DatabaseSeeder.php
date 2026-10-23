<?php

namespace Database\Seeders;

use App\Models\Deal;
use App\Models\MockDeal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $snapshot = [
            'external_id' => '5678',
            'title' => 'Продажа курса',
            'status' => 'NEW',
            'amount' => '49000.00',
            'version' => 18,
            'external_updated_at' => '2026-10-15T15:29:50Z',
        ];

        MockDeal::query()->firstOrCreate(
            ['external_id' => $snapshot['external_id']],
            $snapshot,
        );

        Deal::query()->firstOrCreate(
            ['external_id' => $snapshot['external_id']],
            [
                'title' => $snapshot['title'],
                'status' => $snapshot['status'],
                'amount' => $snapshot['amount'],
                'external_version' => 17,
                'external_updated_at' => '2026-10-14T15:29:50Z',
            ],
        );
    }
}
