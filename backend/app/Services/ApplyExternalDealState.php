<?php

namespace App\Services;

use App\Models\Deal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ApplyExternalDealState
{
    /** @return array{outcome: string, deal: Deal} */
    public function apply(array $external): array
    {
        $externalId = (string) $external['id'];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($external, $externalId): array {
                    $deal = Deal::query()
                        ->where('external_id', $externalId)
                        ->lockForUpdate()
                        ->first();

                    if (! $deal) {
                        $deal = Deal::query()->create([
                            'external_id' => $externalId,
                            'title' => trim($external['title']),
                            'status' => trim($external['status']),
                            'amount' => $this->normalizeAmount($external['amount']),
                            'external_updated_at' => $external['updated_at'],
                            'external_version' => $external['version'],
                        ]);

                        return ['outcome' => 'applied', 'deal' => $deal];
                    }

                    $version = (int) $external['version'];

                    if ($version < $deal->external_version) {
                        return ['outcome' => 'ignored_stale', 'deal' => $deal];
                    }

                    $incoming = [
                        'title' => trim($external['title']),
                        'status' => trim($external['status']),
                        'amount' => $this->normalizeAmount($external['amount']),
                    ];

                    if ($version === $deal->external_version) {
                        $current = [
                            'title' => $deal->title,
                            'status' => $deal->status,
                            'amount' => $this->normalizeAmount($deal->amount),
                        ];

                        return [
                            'outcome' => $incoming === $current ? 'same_version' : 'same_version_conflict',
                            'deal' => $deal,
                        ];
                    }

                    $deal->fill([
                        ...$incoming,
                        'external_updated_at' => $external['updated_at'],
                        'external_version' => $version,
                    ])->save();

                    return ['outcome' => 'applied', 'deal' => $deal->refresh()];
                }, attempts: 3);
            } catch (QueryException $exception) {
                $uniqueViolation = in_array((string) $exception->getCode(), ['23000', '23505'], true);

                if (! $uniqueViolation || $attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Could not apply external deal state.');
    }

    private function normalizeAmount(string|int|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
