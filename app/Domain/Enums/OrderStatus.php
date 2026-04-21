<?php

namespace App\Domain\Enums;

/**
 * Order lifecycle states (matches `orders.status` ENUM).
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    /** Checkout: funds held in escrow for the order (single-seller orchestration terminal state). */
    case PaidInEscrow = 'paid_in_escrow';
    case Processing = 'processing';
    case ShippedOrDelivered = 'shipped_or_delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Disputed = 'disputed';
}
