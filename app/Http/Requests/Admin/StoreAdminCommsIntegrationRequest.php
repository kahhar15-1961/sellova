<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAdminCommsIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'channel' => ['required', 'string', Rule::in(['webhook', 'email'])],
            'webhook_url' => ['nullable', 'url', 'max:512'],
            'email_to' => ['nullable', 'email', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
