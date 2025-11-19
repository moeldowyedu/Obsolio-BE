<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['sometimes', 'uuid', 'exists:agents,id'],
            'job_title' => ['sometimes', 'string', 'max:255'],
            'job_description' => ['nullable', 'string'],
            'organization_id' => ['sometimes', 'uuid', 'exists:organizations,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'reporting_manager_id' => ['nullable', 'uuid', 'exists:users,id'],
            'employment_type' => ['sometimes', 'in:full-time,part-time,on-demand'],
            'schedule_type' => ['sometimes', 'in:one-time,daily,weekly,monthly,quarterly,yearly'],
            'schedule_config' => ['sometimes', 'array'],
            'input_source' => ['sometimes', 'array'],
            'output_destination' => ['sometimes', 'array'],
            'hitl_mode' => ['sometimes', 'in:fully-ai,hitl,standby,in-charge,hybrid'],
            'hitl_supervisor_id' => ['nullable', 'uuid', 'exists:users,id'],
            'hitl_rules' => ['nullable', 'array'],
            'status' => ['sometimes', 'in:draft,active,paused,archived'],
        ];
    }
}
