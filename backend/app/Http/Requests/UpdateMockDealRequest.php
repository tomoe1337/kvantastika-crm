<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMockDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'updated_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }
}
