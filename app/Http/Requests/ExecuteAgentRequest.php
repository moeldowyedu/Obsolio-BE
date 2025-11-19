<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input' => ['required', 'array'],
            'context' => ['nullable', 'array'],
            'async' => ['nullable', 'boolean'],
        ];
    }
}
