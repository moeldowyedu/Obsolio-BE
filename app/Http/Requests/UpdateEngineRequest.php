<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEngineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'provider' => ['sometimes', 'string', 'max:100'],
            'model_name' => ['sometimes', 'string', 'max:255'],
            'version' => ['sometimes', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'capabilities' => ['sometimes', 'array'],
            'capabilities.*' => ['string'],
            'input_types' => ['sometimes', 'array'],
            'input_types.*' => ['string'],
            'output_types' => ['sometimes', 'array'],
            'output_types.*' => ['string'],
            'pricing' => ['sometimes', 'array'],
            'pricing.input_token_price' => ['sometimes', 'numeric', 'min:0'],
            'pricing.output_token_price' => ['sometimes', 'numeric', 'min:0'],
            'limits' => ['sometimes', 'array'],
            'limits.max_tokens' => ['sometimes', 'integer', 'min:1'],
            'limits.rate_limit' => ['sometimes', 'integer', 'min:1'],
            'config' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
