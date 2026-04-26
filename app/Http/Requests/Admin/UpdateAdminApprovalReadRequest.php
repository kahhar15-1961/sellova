<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\AdminActionApproval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAdminApprovalReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AdminActionApproval $approval */
        $approval = $this->route('approval');

        return [
            'last_read_message_id' => [
                'required',
                'integer',
                Rule::exists('admin_action_approval_messages', 'id')->where(
                    static fn ($query) => $query->where('approval_id', $approval->id),
                ),
            ],
        ];
    }
}
