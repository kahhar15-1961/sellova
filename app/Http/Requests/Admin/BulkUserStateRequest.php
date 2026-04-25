<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BulkUserStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required_without:select_all', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
            'select_all' => ['nullable', 'boolean'],
            'filters' => ['nullable', 'array'],
            'filters.q' => ['nullable', 'string', 'max:255'],
            'filters.status' => ['nullable', Rule::in(['active', 'suspended', 'closed'])],
            'status' => ['required', Rule::in(['active', 'suspended', 'closed'])],
            'risk_level' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
