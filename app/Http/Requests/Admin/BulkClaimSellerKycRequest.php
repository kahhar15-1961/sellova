<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Admin\AdminPermission;
use Illuminate\Foundation\Http\FormRequest;

final class BulkClaimSellerKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermissionCode(AdminPermission::SELLERS_VERIFY);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kyc_ids' => ['required', 'array', 'min:1', 'max:50'],
            'kyc_ids.*' => ['required', 'integer', 'distinct', 'exists:kyc_verifications,id'],
        ];
    }
}
