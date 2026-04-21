<?php

namespace App\Domain\Enums;

/**
 * Wallet account status (matches `wallets.status` ENUM).
 */
enum WalletAccountStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';
}
