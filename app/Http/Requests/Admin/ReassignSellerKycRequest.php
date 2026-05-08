<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Admin\AdminPermission;
use Illuminate\Foundation\Http\FormRequest;

final class ReassignSellerKycRequest extends FormRequest
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
            'assignee_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
