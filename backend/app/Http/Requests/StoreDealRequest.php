<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_id' => ['nullable', 'string', 'max:255', Rule::unique('deals', 'external_id')],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }
}
