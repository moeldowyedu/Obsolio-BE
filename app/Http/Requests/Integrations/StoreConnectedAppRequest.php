<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConnectedAppRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create-connected-apps');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'app_name' => ['required', 'string', 'max:255'],
            'app_type' => ['required', 'string', 'in:oauth,api_key,webhook,custom'],
            'provider' => ['nullable', 'string', 'in:github,gitlab,slack,google,microsoft,custom'],
            'description' => ['nullable', 'string', 'max:1000'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
            'scopes' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'callback_url' => ['nullable', 'url', 'max:500'],
            'token_expires_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'organization_id.required' => 'Organization is required',
            'organization_id.exists' => 'Invalid organization',
            'app_name.required' => 'App name is required',
            'app_type.in' => 'Invalid app type. Allowed: oauth, api_key, webhook, custom',
            'provider.in' => 'Invalid provider. Allowed: github, gitlab, slack, google, microsoft, custom',
            'callback_url.url' => 'Invalid callback URL format',
        ];
    }
}
