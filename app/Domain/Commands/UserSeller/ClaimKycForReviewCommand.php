<?php

declare(strict_types=1);

namespace App\Domain\Commands\UserSeller;

/**
 * Locks a KYC case for review (submitted → under_review).
 */
final readonly class ClaimKycForReviewCommand
{
    public function __construct(
        public int $kycVerificationId,
        public int $reviewerId,
        public ?string $correlationId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
