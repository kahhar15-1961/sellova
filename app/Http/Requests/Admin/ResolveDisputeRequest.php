<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class ResolveDisputeRequest extends FormRequest
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
            'resolution' => ['required', Rule::in(['buyer_wins', 'seller_wins'])],
            'reason_code' => ['required', 'string', 'max:64'],
            'notes' => ['required', 'string', 'min:10', 'max:8000'],
        ];
    }
}
