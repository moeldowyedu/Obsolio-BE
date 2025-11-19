<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveHITLRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewer_comments' => ['nullable', 'string'],
            'modified_output' => ['nullable', 'array'],
        ];
    }
}
