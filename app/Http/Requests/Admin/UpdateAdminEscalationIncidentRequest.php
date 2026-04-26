<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAdminEscalationIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'incident_id' => ['required', 'integer', 'exists:admin_escalation_incidents,id'],
            'action' => ['required', 'string', Rule::in(['acknowledge', 'resolve', 'reassign'])],
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id', 'required_if:action,reassign'],
            'resolution_reason' => ['nullable', 'string', 'max:2000', 'required_if:action,resolve'],
        ];
    }
}
