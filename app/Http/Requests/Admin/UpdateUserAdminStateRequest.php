<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class UpdateUserAdminStateRequest extends FormRequest
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
            'status' => ['required', Rule::in(['active', 'suspended', 'closed'])],
            'risk_level' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
