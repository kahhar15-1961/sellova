<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\AdminReasonCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSellerStoreStateRequest extends FormRequest
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
            'store_status' => ['required', Rule::in(['active', 'suspended'])],
            'reason_code' => ['required', Rule::in(AdminReasonCatalog::sellerStoreCodes())],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
