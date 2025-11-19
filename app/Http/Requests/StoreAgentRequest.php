<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:custom,marketplace,template'],
            'engines_used' => ['required', 'array', 'min:1'],
            'engines_used.*' => ['uuid', 'exists:engines,id'],
            'config' => ['required', 'array'],
            'config.system_prompt' => ['required', 'string'],
            'config.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'config.max_tokens' => ['nullable', 'integer', 'min:1'],
            'input_schema' => ['nullable', 'array'],
            'output_schema' => ['nullable', 'array'],
            'rubric_config' => ['nullable', 'array'],
            'status' => ['required', 'in:draft,active,inactive,archived'],
        ];
    }
}
