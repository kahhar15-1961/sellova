<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\UserSeller\UpdateSellerProfileCommand;
use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

final class UpdateSellerProfileRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $ownerUserId): UpdateSellerProfileCommand
    {
        $p = self::validate($request);
        $display = isset($p['display_name']) ? trim((string) $p['display_name']) : null;
        if ($display === '') {
            $display = null;
        }
        $legal = isset($p['legal_name']) ? trim((string) $p['legal_name']) : null;
        if ($legal === '') {
            $legal = null;
        }

        return new UpdateSellerProfileCommand(
            ownerUserId: $ownerUserId,
            displayName: $display,
            legalName: $legal,
        );
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'display_name' => new Optional([new Type('string'), new Length(min: 1, max: 191)]),
                'legal_name' => new Optional([new Type('string'), new Length(min: 1, max: 255)]),
                'correlation_id' => new Optional([new Type('string'), new Length(max: 191)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}
