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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $channel = (string) $this->input('channel');
            if ($channel === 'webhook' && ! $this->filled('webhook_url')) {
                $validator->errors()->add('webhook_url', 'Webhook URL is required when channel is webhook.');
            }
            if ($channel === 'email' && ! $this->filled('email_to')) {
                $validator->errors()->add('email_to', 'Email target is required when channel is email.');
            }
        });
    }
}
