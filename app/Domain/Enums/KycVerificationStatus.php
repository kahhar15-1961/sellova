<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Matches {@code kyc_verifications.status} ENUM.
 */
enum KycVerificationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
