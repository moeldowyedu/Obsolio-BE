<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'team_lead_id' => ['nullable', 'uuid', 'exists:users,id'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
