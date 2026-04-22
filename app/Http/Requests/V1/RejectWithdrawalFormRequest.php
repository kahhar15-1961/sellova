<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Withdrawal\RejectWithdrawalCommand;
use App\Http\Validation\AbstractValidatedRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

final class RejectWithdrawalFormRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $withdrawalRequestId, User $reviewer): RejectWithdrawalCommand
    {
        $payload = self::validate($request);

        return new RejectWithdrawalCommand(
            withdrawalRequestId: $withdrawalRequestId,
            reviewerUserId: (int) $reviewer->id,
            idempotencyKey: (string) $payload['idempotency_key'],
            reason: (string) $payload['reason'],
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'idempotency_key' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 191)],
                'reason' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 2000)],
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}
