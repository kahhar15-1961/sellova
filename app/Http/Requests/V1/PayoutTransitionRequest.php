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

final class PayoutTransitionRequest extends AbstractValidatedRequest
{
    /**
     * @return array{idempotency_key: string, provider_reference?: string}
     */
    public static function payload(Request $request): array
    {
        $p = self::validate($request);
        $out = ['idempotency_key' => (string) $p['idempotency_key']];
        if (isset($p['provider_reference'])) {
            $out['provider_reference'] = (string) $p['provider_reference'];
        }

        return $out;
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'idempotency_key' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 191)],
                'provider_reference' => new Optional([new Type('string'), new Length(max: 191)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}
