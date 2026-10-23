<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_time',
        'external_id',
        'payload',
        'status',
        'outcome',
        'attempts',
        'last_error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'immutable_datetime',
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
