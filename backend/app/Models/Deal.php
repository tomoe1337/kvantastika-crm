<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'title',
        'status',
        'amount',
        'external_updated_at',
        'external_version',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'external_updated_at' => 'immutable_datetime',
            'external_version' => 'integer',
        ];
    }

    public function syncOperations(): HasMany
    {
        return $this->hasMany(SyncOperation::class);
    }

    public function latestSyncOperation(): HasOne
    {
        return $this->hasOne(SyncOperation::class)->latestOfMany();
    }
}
