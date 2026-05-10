<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Admin\AdminPermission;
use Illuminate\Foundation\Http\FormRequest;

final class ReviewSellerKycRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:approved,rejected,resubmission_required'],
            'reason' => ['nullable', 'string', 'max:8000', 'required_if:decision,rejected,resubmission_required'],
        ];
    }
}
