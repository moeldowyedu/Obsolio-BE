<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowRequest extends FormRequest
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
            'workflow_definition' => ['sometimes', 'array'],
            'workflow_definition.nodes' => ['sometimes', 'array', 'min:1'],
            'workflow_definition.edges' => ['sometimes', 'array'],
            'input_schema' => ['nullable', 'array'],
            'output_schema' => ['nullable', 'array'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'in:draft,active,inactive,archived'],
        ];
    }
}
