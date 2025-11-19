<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'job_title' => ['required', 'string', 'max:255'],
            'job_description' => ['nullable', 'string'],
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'reporting_manager_id' => ['nullable', 'uuid', 'exists:users,id'],
            'employment_type' => ['required', 'in:full-time,part-time,on-demand'],
            'schedule_type' => ['required', 'in:one-time,daily,weekly,monthly,quarterly,yearly'],
            'schedule_config' => ['required', 'array'],
            'input_source' => ['required', 'array'],
            'input_source.type' => ['required', 'string'],
            'output_destination' => ['required', 'array'],
            'output_destination.type' => ['required', 'string'],
            'hitl_mode' => ['required', 'in:fully-ai,hitl,standby,in-charge,hybrid'],
            'hitl_supervisor_id' => ['required_if:hitl_mode,hitl,standby,in-charge,hybrid', 'nullable', 'uuid', 'exists:users,id'],
            'hitl_rules' => ['nullable', 'array'],
            'status' => ['required', 'in:draft,active,paused,archived'],
        ];
    }
}
