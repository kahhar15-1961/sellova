<?php

declare(strict_types=1);

namespace App\Domain\Commands\UserSeller;

/**
 * Reassigns an existing KYC case to a reviewer.
 */
final readonly class ReassignKycForReviewCommand
{
    public function __construct(
        public int $kycVerificationId,
        public int $actorId,
        public int $assigneeId,
        public ?string $correlationId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
