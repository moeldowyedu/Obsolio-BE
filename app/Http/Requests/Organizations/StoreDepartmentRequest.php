<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'parent_department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'head_id' => ['nullable', 'uuid', 'exists:users,id'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
