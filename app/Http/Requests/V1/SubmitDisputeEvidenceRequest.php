<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Commands\Dispute\SubmitDisputeEvidenceCommand;
use App\Domain\Value\DisputeEvidenceItem;
use App\Http\Validation\AbstractValidatedRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

final class SubmitDisputeEvidenceRequest extends AbstractValidatedRequest
{
    public static function toCommand(Request $request, int $disputeCaseId, User $actor): SubmitDisputeEvidenceCommand
    {
        $payload = self::validate($request);
        /** @var list<array<string, mixed>> $rows */
        $rows = $payload['evidence'];
        $items = [];
        foreach ($rows as $row) {
            $items[] = new DisputeEvidenceItem(
                evidenceType: (string) $row['evidence_type'],
                contentText: isset($row['content_text']) ? (string) $row['content_text'] : null,
                storagePath: isset($row['storage_path']) ? (string) $row['storage_path'] : null,
                checksumSha256: isset($row['checksum_sha256']) ? (string) $row['checksum_sha256'] : null,
            );
        }

        return new SubmitDisputeEvidenceCommand(
            disputeCaseId: $disputeCaseId,
            submittedByUserId: (int) $actor->id,
            evidence: $items,
        );
    }

    protected static function constraint(): Constraint
    {
        $line = new Collection([
            'fields' => [
                'evidence_type' => [new NotBlank(), new Type('string'), new Choice([
                    'text', 'image', 'video', 'document', 'tracking',
                ])],
                'content_text' => new Optional([new Type('string')]),
                'storage_path' => new Optional([new Type('string')]),
                'checksum_sha256' => new Optional([new Type('string')]),
            ],
            'allowMissingFields' => true,
            'allowExtraFields' => false,
        ]);

        return new Collection([
            'fields' => [
                'evidence' => [new NotBlank(), new Type('array'), new Count(min: 1), new All($line)],
            ],
            'allowMissingFields' => false,
            'allowExtraFields' => false,
        ]);
    }
}
