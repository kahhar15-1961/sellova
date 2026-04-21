<?php

namespace App\Domain\Enums;

/**
 * Values for `escrow_events.event_type` (matches canonical ENUM).
 */
enum EscrowEventType: string
{
    case Initiated = 'initiated';
    case Hold = 'hold';
    case Release = 'release';
    case Refund = 'refund';
    case DisputeOpened = 'dispute_opened';
    case DisputeResolved = 'dispute_resolved';
}
