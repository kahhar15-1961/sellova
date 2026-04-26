<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAdminRunbookStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'runbook_id' => ['required', 'integer', 'min:1'],
            'step_order' => ['required', 'integer', 'min:1', 'max:1000'],
            'instruction' => ['required', 'string', 'max:4000'],
            'is_required' => ['required', 'boolean'],
            'evidence_required' => ['required', 'boolean'],
        ];
    }
}
