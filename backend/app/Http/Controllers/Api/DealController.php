<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Deal::query()->with('latestSyncOperation');

        if (! empty($filters['q'])) {
            $term = mb_strtolower(trim($filters['q']));
            $pattern = '%'.$term.'%';

            $query->where(function ($deals) use ($pattern): void {
                $deals->whereRaw('LOWER(title) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(external_id, \'\')) LIKE ?', [$pattern]);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json($query->orderByDesc('updated_at')->paginate($filters['per_page'] ?? 15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => ['nullable', 'string', 'max:255', Rule::unique('deals', 'external_id')],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $deal = Deal::query()->create([
            ...$validated,
            'external_id' => filled($validated['external_id'] ?? null) ? trim($validated['external_id']) : null,
            'title' => trim($validated['title']),
            'status' => trim($validated['status']),
            'external_version' => 0,
        ]);

        return response()->json($deal, 201);
    }

    public function show(Deal $deal): JsonResponse
    {
        $deal->load('latestSyncOperation');

        return response()->json([
            'deal' => $deal,
            'sync_operations' => $deal->syncOperations()->latest()->limit(5)->get(),
            'webhook_events' => $deal->external_id
                ? \App\Models\WebhookEvent::query()->where('external_id', $deal->external_id)->latest()->limit(5)->get()
                : [],
        ]);
    }

    public function update(Request $request, Deal $deal): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'max:32'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        foreach (['title', 'status'] as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = trim($validated[$field]);
            }
        }

        $deal->fill($validated)->save();

        return response()->json($deal->refresh()->load('latestSyncOperation'));
    }
}
