<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:255'],
            'event_time' => ['required', 'date'],
            'deal' => ['required', 'array'],
            'deal.id' => ['required', 'string', 'max:255'],
            'deal.version' => ['required', 'integer', 'min:1'],
            'deal.updated_at' => ['required', 'date'],
            'deal.title' => ['required', 'string', 'max:255'],
            'deal.status' => ['required', 'string', 'max:32'],
            'deal.amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }
}
