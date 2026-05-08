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
    /** @deprecated Use EscrowFunded for escrow marketplace flows. */
    case PaidInEscrow = 'paid_in_escrow';
    case EscrowFunded = 'escrow_funded';
    case Processing = 'processing';
    case DeliverySubmitted = 'delivery_submitted';
    case BuyerReview = 'buyer_review';
    /** @deprecated Physical delivery is represented by processing + shipment fields. */
    case ShippedOrDelivered = 'shipped_or_delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Disputed = 'disputed';
}
