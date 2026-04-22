<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Order\MarkOrderPaidCommand;
use App\Http\Validation\AbstractValidatedRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

final class MarkOrderPaidRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $orderId, User $actor): MarkOrderPaidCommand
    {
        $payload = self::validate($request);

        return new MarkOrderPaidCommand(
            orderId: $orderId,
            paymentTransactionId: (int) $payload['payment_transaction_id'],
            correlationId: isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
            actorUserId: (int) $actor->id,
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'payment_transaction_id' => [new NotBlank(), new Type('numeric'), new Positive()],
                'correlation_id' => new Optional([new Type('string'), new Length(max: 191)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}
