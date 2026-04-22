<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Dispute\EscalateDisputeCommand;
use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

final class EscalateDisputeRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $disputeCaseId): EscalateDisputeCommand
    {
        self::validate($request);

        return new EscalateDisputeCommand(disputeCaseId: $disputeCaseId);
    }

    protected static function constraint(): Constraint
    {
        return new Collection([
            'fields' => [
                'note' => new Optional([new Type('string'), new Length(max: 5000)]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);
    }
}
