<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:custom,marketplace,template'],
            'engines_used' => ['sometimes', 'array', 'min:1'],
            'engines_used.*' => ['uuid', 'exists:engines,id'],
            'config' => ['sometimes', 'array'],
            'config.system_prompt' => ['sometimes', 'string'],
            'config.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'config.max_tokens' => ['nullable', 'integer', 'min:1'],
            'input_schema' => ['nullable', 'array'],
            'output_schema' => ['nullable', 'array'],
            'rubric_config' => ['nullable', 'array'],
            'status' => ['sometimes', 'in:draft,active,inactive,archived'],
        ];
    }
}
