<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Withdrawal\RequestWithdrawalCommand;
use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

final class StoreWithdrawalRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request): RequestWithdrawalCommand
    {
        $payload = self::validate($request);

        return new RequestWithdrawalCommand(
            sellerProfileId: (int) $payload['seller_profile_id'],
            walletId: (int) $payload['wallet_id'],
            amount: (string) $payload['amount'],
            currency: (string) $payload['currency'],
            idempotencyKey: (string) $payload['idempotency_key'],
            feeAmount: isset($payload['fee_amount']) ? (string) $payload['fee_amount'] : null,
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'seller_profile_id' => [new NotBlank(), new Type('numeric'), new Positive()],
                'wallet_id' => [new NotBlank(), new Type('numeric'), new Positive()],
                'amount' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 24)],
                'currency' => [new NotBlank(), new Type('string'), new Length(exactly: 3)],
                'idempotency_key' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 191)],
                'fee_amount' => new Optional([new Type('string'), new Length(max: 24)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}
