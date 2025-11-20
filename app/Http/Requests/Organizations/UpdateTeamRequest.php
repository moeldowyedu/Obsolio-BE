<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['sometimes', 'uuid', 'exists:organizations,id'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'team_lead_id' => ['nullable', 'uuid', 'exists:users,id'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
