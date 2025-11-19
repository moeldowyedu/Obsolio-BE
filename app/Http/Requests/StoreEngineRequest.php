<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEngineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'max:100'],
            'model_name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'capabilities' => ['required', 'array'],
            'capabilities.*' => ['string'],
            'input_types' => ['required', 'array'],
            'input_types.*' => ['string'],
            'output_types' => ['required', 'array'],
            'output_types.*' => ['string'],
            'pricing' => ['required', 'array'],
            'pricing.input_token_price' => ['required', 'numeric', 'min:0'],
            'pricing.output_token_price' => ['required', 'numeric', 'min:0'],
            'limits' => ['required', 'array'],
            'limits.max_tokens' => ['required', 'integer', 'min:1'],
            'limits.rate_limit' => ['required', 'integer', 'min:1'],
            'config' => ['nullable', 'array'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
