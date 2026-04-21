<?php

namespace App\Domain\Commands\UserSeller;

/**
 * Input contract for {@see \App\Services\UserSeller\UserSellerService::reviewKyc}.
 */
final readonly class ReviewKycCommand
{
    public function __construct(
        public int $kycVerificationId,
        public int $reviewerId,
        public string $decision,
        public ?string $reason,
    ) {
    }
}
