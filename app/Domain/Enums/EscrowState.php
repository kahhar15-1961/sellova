<?php

namespace App\Domain\Enums;

/**
 * Escrow aggregate states (matches `escrow_accounts.state` ENUM).
 */
enum EscrowState: string
{
    case Initiated = 'initiated';
    case Held = 'held';
    case Released = 'released';
    case Refunded = 'refunded';
    case UnderDispute = 'under_dispute';
}
