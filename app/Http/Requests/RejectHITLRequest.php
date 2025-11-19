<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectHITLRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewer_comments' => ['required', 'string'],
            'escalate' => ['nullable', 'boolean'],
        ];
    }
}
