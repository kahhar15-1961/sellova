<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\AdminReasonCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class ModerateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string|In>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'archived', 'published'])],
            'policy_code' => ['required', Rule::in(AdminReasonCatalog::productPolicyCodes())],
            'reason' => ['required', 'string', 'max:1000'],
            'evidence_notes' => ['required_if:policy_code,policy_violation,counterfeit_risk', 'nullable', 'string', 'max:2000'],
        ];
    }
}
