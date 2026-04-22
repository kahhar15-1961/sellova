<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Dispute\OpenDisputeCommand;
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

final class OpenDisputeRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $orderId, User $actor): OpenDisputeCommand
    {
        $payload = self::validate($request);

        return new OpenDisputeCommand(
            orderId: $orderId,
            orderItemId: isset($payload['order_item_id']) ? (int) $payload['order_item_id'] : null,
            openedByUserId: (int) $actor->id,
            reasonCode: (string) $payload['reason_code'],
            idempotencyKey: isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : null,
            correlationId: isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'reason_code' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 64)],
                'order_item_id' => new Optional([new Type('numeric'), new Positive()]),
                'idempotency_key' => new Optional([new Type('string'), new Length(max: 191)]),
                'correlation_id' => new Optional([new Type('string'), new Length(max: 191)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}
