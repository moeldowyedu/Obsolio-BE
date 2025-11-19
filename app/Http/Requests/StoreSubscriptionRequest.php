<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_name' => ['required', 'string', 'in:free,starter,professional,enterprise'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'payment_method' => ['required', 'string'],
        ];
    }
}
