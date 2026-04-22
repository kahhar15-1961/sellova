<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Withdrawal\ReviewWithdrawalCommand;
use App\Domain\Enums\WithdrawalReviewDecision;
use App\Http\Validation\AbstractValidatedRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

final class ReviewWithdrawalFormRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $withdrawalRequestId, User $reviewer): ReviewWithdrawalCommand
    {
        $payload = self::validate($request);
        $decision = WithdrawalReviewDecision::from((string) $payload['decision']);

        return new ReviewWithdrawalCommand(
            withdrawalRequestId: $withdrawalRequestId,
            reviewerId: (int) $reviewer->id,
            decision: $decision,
            reason: isset($payload['reason']) ? (string) $payload['reason'] : null,
            idempotencyKey: (string) $payload['idempotency_key'],
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'decision' => [new NotBlank(), new Type('string'), new Choice(['approved', 'rejected'])],
                'idempotency_key' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 191)],
                'reason' => new Optional([new Type('string'), new Length(max: 2000)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}
