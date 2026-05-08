<?php

declare(strict_types=1);

namespace App\Domain\Commands\UserSeller;

/**
 * Claims multiple KYC cases for a reviewer.
 *
 * @param list<int> $kycVerificationIds
 */
final readonly class BulkClaimKycForReviewCommand
{
    /**
     * @param list<int> $kycVerificationIds
     */
    public function __construct(
        public array $kycVerificationIds,
        public int $reviewerId,
        public ?string $correlationId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
