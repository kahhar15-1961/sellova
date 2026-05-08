<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum WalletTopUpReviewDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
