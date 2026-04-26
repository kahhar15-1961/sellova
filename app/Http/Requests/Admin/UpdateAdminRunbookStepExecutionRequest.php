<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAdminRunbookStepExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'incident_id' => ['required', 'integer', 'exists:admin_escalation_incidents,id'],
            'step_execution_id' => ['required', 'integer', 'exists:admin_runbook_step_executions,id'],
            'action' => ['required', 'string', Rule::in(['complete_step'])],
            'evidence_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
