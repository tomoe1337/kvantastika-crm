<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MockDeal extends Model
{
    protected $fillable = [
        'external_id',
        'title',
        'status',
        'amount',
        'version',
        'external_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'version' => 'integer',
            'external_updated_at' => 'immutable_datetime',
        ];
    }
}
