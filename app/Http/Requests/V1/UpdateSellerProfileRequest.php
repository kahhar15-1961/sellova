<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\UserSeller\UpdateSellerProfileCommand;
use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

final class UpdateSellerProfileRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $ownerUserId): UpdateSellerProfileCommand
    {
        $p = self::validate($request);
        $display = isset($p['display_name']) ? trim((string) $p['display_name']) : null;
        $storeName = isset($p['store_name']) ? trim((string) $p['store_name']) : null;
        if (($display === null || $display === '') && $storeName !== null && $storeName !== '') {
            $display = $storeName;
        }
        if ($display === '') {
            $display = null;
        }
        $legal = isset($p['legal_name']) ? trim((string) $p['legal_name']) : null;
        $storeDescription = isset($p['store_description']) ? trim((string) $p['store_description']) : null;
        if (($legal === null || $legal === '') && $storeDescription !== null && $storeDescription !== '') {
            $legal = $storeDescription;
        }
        if ($legal === '') {
            $legal = null;
        }
        $storeLogoUrl = isset($p['store_logo_url']) ? trim((string) $p['store_logo_url']) : null;
        if ($storeLogoUrl === '') {
            $storeLogoUrl = null;
        }
        $bannerImageUrl = isset($p['banner_image_url']) ? trim((string) $p['banner_image_url']) : null;
        if ($bannerImageUrl === '') {
            $bannerImageUrl = null;
        }
        $contactEmail = self::nullableString($p['contact_email'] ?? null);
        $contactPhone = self::nullableString($p['contact_phone'] ?? null);
        $addressLine = self::nullableString($p['address_line'] ?? $p['store_address'] ?? null);
        $city = self::nullableString($p['city'] ?? null);
        $region = self::nullableString($p['region'] ?? null);
        $postalCode = self::nullableString($p['postal_code'] ?? null);
        $country = self::nullableString($p['country'] ?? null);

        return new UpdateSellerProfileCommand(
            ownerUserId: $ownerUserId,
            displayName: $display,
            legalName: $legal,
            storeLogoUrl: $storeLogoUrl,
            bannerImageUrl: $bannerImageUrl,
            contactEmail: $contactEmail,
            contactPhone: $contactPhone,
            addressLine: $addressLine,
            city: $city,
            region: $region,
            postalCode: $postalCode,
            country: $country,
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'display_name' => new Optional([new Type('string'), new Length(min: 1, max: 191)]),
                'legal_name' => new Optional([new Type('string'), new Length(min: 1, max: 255)]),
                'store_name' => new Optional([new Type('string'), new Length(min: 1, max: 191)]),
                'store_description' => new Optional([new Type('string'), new Length(max: 255)]),
                'store_logo_url' => new Optional([new Type('string'), new Length(max: 512)]),
                'banner_image_url' => new Optional([new Type('string'), new Length(max: 512)]),
                'contact_email' => new Optional([new Type('string'), new Email(), new Length(max: 191)]),
                'contact_phone' => new Optional([new Type('string'), new Length(max: 40)]),
                'address_line' => new Optional([new Type('string'), new Length(max: 255)]),
                'store_address' => new Optional([new Type('string'), new Length(max: 255)]),
                'city' => new Optional([new Type('string'), new Length(max: 120)]),
                'region' => new Optional([new Type('string'), new Length(max: 120)]),
                'postal_code' => new Optional([new Type('string'), new Length(max: 40)]),
                'country' => new Optional([new Type('string'), new Length(max: 120)]),
                'correlation_id' => new Optional([new Type('string'), new Length(max: 191)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
