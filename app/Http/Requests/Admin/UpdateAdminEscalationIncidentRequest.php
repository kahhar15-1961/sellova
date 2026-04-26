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
            'incident_id' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'string', Rule::in(['acknowledge', 'resolve', 'reassign'])],
            'assignee_user_id' => ['nullable', 'integer', 'min:1'],
            'resolution_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
