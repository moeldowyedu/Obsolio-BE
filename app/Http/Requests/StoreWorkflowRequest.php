<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
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
            'workflow_definition' => ['required', 'array'],
            'workflow_definition.nodes' => ['required', 'array', 'min:1'],
            'workflow_definition.edges' => ['required', 'array'],
            'input_schema' => ['nullable', 'array'],
            'output_schema' => ['nullable', 'array'],
            'version' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,active,inactive,archived'],
        ];
    }
}
