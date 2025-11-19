<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'uuid', 'exists:marketplace_listings,id'],
            'payment_method' => ['required', 'string', 'in:credit_card,subscription,credits'],
        ];
    }
}
