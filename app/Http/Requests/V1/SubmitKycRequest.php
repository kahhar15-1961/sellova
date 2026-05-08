<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\UserSeller\SubmitKycCommand;
use App\Domain\Value\KycDocumentItem;
use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;

final class SubmitKycRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $sellerProfileId): SubmitKycCommand
    {
        $payload = self::validate($request);
        $documents = [];
        foreach ($payload['documents'] as $row) {
            $documents[] = new KycDocumentItem(
                docType: (string) $row['doc_type'],
                storagePath: (string) $row['storage_path'],
                checksumSha256: trim((string) ($row['checksum_sha256'] ?? '')),
            );
        }

        return SubmitKycCommand::fromItems($sellerProfileId, ...$documents);
    }

    protected static function constraint(): Constraint
    {
        $document = new Collection([
            'fields' => [
                'doc_type' => [
                    new NotBlank(),
                    new Type('string'),
                    new Regex('/^(id_front|id_back|selfie|business_license|address_proof|nid_front|nid_back|nid_selfie|license_front|license_back|license_selfie|passport_page|passport_selfie)$/'),
                ],
                'storage_path' => [new NotBlank(), new Type('string'), new Length(max: 512)],
                'checksum_sha256' => [new Optional([new Type('string'), new Length(max: 128)])],
            ],
            'allowMissingFields' => false,
            'allowExtraFields' => false,
        ]);

        return new Collection([
            'fields' => [
                'seller_profile_id' => new Optional([new Type('numeric')]),
                'documents' => [new NotBlank(), new Type('array'), new Count(min: 2), new All($document)],
            ],
            'allowMissingFields' => false,
            'allowExtraFields' => false,
        ]);
    }
}
