<?php

namespace App\Domain\Enums;

/**
 * Wallet hold lifecycle (matches `wallet_holds.status` ENUM).
 */
enum WalletHoldStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Consumed = 'consumed';
    case Cancelled = 'cancelled';
}
