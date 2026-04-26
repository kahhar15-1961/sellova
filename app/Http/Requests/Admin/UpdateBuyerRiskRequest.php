<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\AdminReasonCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBuyerRiskRequest extends FormRequest
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
            'status' => ['required', Rule::in(['active', 'suspended', 'closed'])],
            'risk_level' => ['required', Rule::in(['low', 'medium', 'high'])],
            'restricted_checkout' => ['required', 'boolean'],
            'reason_code' => ['required', Rule::in(AdminReasonCatalog::buyerRiskCodes())],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
