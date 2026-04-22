<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

final class DisputeResolveSplitFormRequest extends AbstractValidatedRequest
{
    /**
     * @return array{buyer_refund_amount: string, currency: string, reason_code: string, notes: string, idempotency_key: string, resolution_notes: ?string}
     */
    public static function validated(Request $request): array
    {
        $payload = self::validate($request);

        return [
            'buyer_refund_amount' => (string) $payload['buyer_refund_amount'],
            'currency' => (string) $payload['currency'],
            'reason_code' => (string) $payload['reason_code'],
            'notes' => (string) $payload['notes'],
            'idempotency_key' => (string) $payload['idempotency_key'],
            'resolution_notes' => isset($payload['resolution_notes']) ? (string) $payload['resolution_notes'] : null,
        ];
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'buyer_refund_amount' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 24)],
                'currency' => [new NotBlank(), new Type('string'), new Length(exactly: 3)],
                'reason_code' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 64)],
                'notes' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 8000)],
                'idempotency_key' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 191)],
                'resolution_notes' => new Optional([new Type('string'), new Length(max: 8000)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}
