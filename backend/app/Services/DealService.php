<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DealService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Deal::query()->with('latestSyncOperation');

        if (filled($filters['q'] ?? null)) {
            $pattern = '%'.mb_strtolower(trim($filters['q'])).'%';

            $query->where(function ($deals) use ($pattern): void {
                $deals->whereRaw('LOWER(title) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(external_id, \'\')) LIKE ?', [$pattern]);
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('updated_at')->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Deal
    {
        return Deal::query()->create([
            'external_id' => filled($data['external_id'] ?? null) ? trim($data['external_id']) : null,
            'title' => trim($data['title']),
            'status' => trim($data['status']),
            'amount' => $data['amount'],
            'external_version' => 0,
        ]);
    }

    public function update(Deal $deal, array $data): Deal
    {
        foreach (['title', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim($data[$field]);
            }
        }

        $deal->fill($data)->save();

        return $deal->refresh()->load('latestSyncOperation');
    }

    public function detail(Deal $deal): array
    {
        $deal->load('latestSyncOperation');

        return [
            'deal' => $deal,
            'sync_operations' => $deal->syncOperations()->latest()->limit(5)->get(),
            'webhook_events' => $deal->external_id
                ? WebhookEvent::query()->where('external_id', $deal->external_id)->latest()->limit(5)->get()
                : [],
        ];
    }
}
