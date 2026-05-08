<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Wallet top-up request lifecycle.
 */
enum WalletTopUpRequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
