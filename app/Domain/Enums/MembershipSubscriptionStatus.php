<?php

namespace App\Domain\Enums;

/**
 * Seller membership subscription lifecycle (matches `membership_subscriptions.status` ENUM).
 */
enum MembershipSubscriptionStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Suspended = 'suspended';
}
