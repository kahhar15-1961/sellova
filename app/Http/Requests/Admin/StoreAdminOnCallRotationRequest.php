<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\AdminOnCallRotation;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAdminOnCallRotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_code' => ['required', 'string', 'max:64'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'start_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'end_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $start = (int) $this->input('start_hour');
            $end = (int) $this->input('end_hour');
            if ($end < $start) {
                $validator->errors()->add('end_hour', 'End hour must be greater than or equal to start hour.');
                return;
            }

            $overlap = AdminOnCallRotation::query()
                ->where('role_code', (string) $this->input('role_code'))
                ->where('weekday', (int) $this->input('weekday'))
                ->where('is_active', true)
                ->where('start_hour', '<=', $end)
                ->where('end_hour', '>=', $start)
                ->exists();

            if ($overlap) {
                $validator->errors()->add('start_hour', 'Active rotation overlaps with an existing on-call window for this role/day.');
            }
        });
    }
}
