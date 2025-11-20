<?php

namespace App\Http\Requests\Integrations;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update-webhooks');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => [
                'string',
                'in:agent.deployed,agent.updated,agent.executed,job.started,job.completed,job.failed,approval.requested,approval.approved,approval.rejected,workflow.started,workflow.completed,workflow.failed,error.occurred,user.created,user.updated,project.created,project.updated',
            ],
            'headers' => ['sometimes', 'nullable', 'array'],
            'headers.*.key' => ['required_with:headers', 'string', 'max:255'],
            'headers.*.value' => ['required_with:headers', 'string', 'max:500'],
            'secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'url.url' => 'Please enter a valid URL.',
            'events.min' => 'Please select at least one event.',
            'events.*.in' => 'One or more selected events are invalid.',
            'headers.*.key.required_with' => 'Header key is required when headers are provided.',
            'headers.*.value.required_with' => 'Header value is required when headers are provided.',
        ];
    }
}
