<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAdminEscalationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'queue_code' => ['required', 'string', Rule::in(['disputes', 'withdrawals', 'approvals'])],
            'default_severity' => ['required', 'string', Rule::in(['medium', 'high', 'critical'])],
            'auto_assign_on_call' => ['required', 'boolean'],
            'on_call_role_code' => ['nullable', 'string', 'max:64'],
            'ack_sla_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'resolve_sla_minutes' => ['required', 'integer', 'min:1', 'max:43200'],
            'comms_integration_id' => ['nullable', 'integer'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
