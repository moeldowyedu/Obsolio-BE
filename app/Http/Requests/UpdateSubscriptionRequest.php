<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_name' => ['sometimes', 'string', 'in:free,starter,professional,enterprise'],
            'billing_cycle' => ['sometimes', 'in:monthly,yearly'],
            'auto_renew' => ['sometimes', 'boolean'],
        ];
    }
}
