<?php

namespace App\Domain\Value;

/**
 * One dispute evidence submission line.
 */
final readonly class DisputeEvidenceItem
{
    public function __construct(
        public string $evidenceType,
        public ?string $contentText,
        public ?string $storagePath,
        public ?string $checksumSha256,
    ) {
    }
}
