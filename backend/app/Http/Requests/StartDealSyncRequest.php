<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartDealSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['nullable', 'in:success,rate_limit,server_error'],
        ];
    }
}
