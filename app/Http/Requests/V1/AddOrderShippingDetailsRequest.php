<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Order\AddOrderShippingDetailsCommand;
use App\Http\Validation\AbstractValidatedRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

final class AddOrderShippingDetailsRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $orderId, User $actor): AddOrderShippingDetailsCommand
    {
        $payload = self::validate($request);

        return new AddOrderShippingDetailsCommand(
            orderId: $orderId,
            actorUserId: (int) $actor->id,
            courierCompany: (string) $payload['courier_company'],
            trackingId: (string) $payload['tracking_id'],
            trackingUrl: isset($payload['tracking_url']) ? (string) $payload['tracking_url'] : null,
            shippingNote: isset($payload['note']) ? (string) $payload['note'] : null,
            shippedAtIso: isset($payload['shipping_date']) ? (string) $payload['shipping_date'] : null,
            correlationId: isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'courier_company' => [new NotBlank(), new Type('string'), new Length(max: 191)],
                'tracking_id' => [new NotBlank(), new Type('string'), new Length(max: 191)],
                'tracking_url' => new Optional([new Type('string'), new Length(max: 512)]),
                'note' => new Optional([new Type('string'), new Length(max: 5000)]),
                'shipping_date' => new Optional([new Type('string'), new Length(max: 64)]),
                'correlation_id' => new Optional([new Type('string'), new Length(max: 191)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}
