<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\UserSeller\CreateSellerProfileCommand;
use App\Domain\Value\SellerProfileDraft;
use App\Http\Validation\AbstractValidatedRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;

final class CreateSellerProfileRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $userId, User $actor): CreateSellerProfileCommand
    {
        $payload = self::validate($request);
        $country = strtoupper(trim((string) ($payload['country_code'] ?? 'BD')));
        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            $country = 'BD';
        }
        $currency = strtoupper(trim((string) ($payload['default_currency'] ?? 'BDT')));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'BDT';
        }

        return new CreateSellerProfileCommand(
            userId: $userId,
            draft: new SellerProfileDraft(
                displayName: trim((string) $payload['display_name']),
                legalName: isset($payload['legal_name']) ? trim((string) $payload['legal_name']) : null,
                countryCode: $country,
                defaultCurrency: $currency,
            ),
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'display_name' => [new Type('string'), new Length(min: 2, max: 191)],
                'legal_name' => new Optional([new Type('string'), new Length(min: 2, max: 191)]),
                'country_code' => new Optional([new Type('string'), new Regex('/^[A-Za-z]{2}$/')]),
                'default_currency' => new Optional([new Type('string'), new Regex('/^[A-Za-z]{3}$/')]),
                'correlation_id' => new Optional([new Type('string'), new Length(max: 191)]),
            ],
            'allowMissingFields' => false,
            'allowExtraFields' => false,
        ]);
    }
}
