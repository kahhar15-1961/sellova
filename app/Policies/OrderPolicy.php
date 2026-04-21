<?php

namespace App\Policies;

use App\Auth\OrderParticipant;
use App\Domain\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    /**
     * Buyer, any seller on the order line items, or platform staff may inspect the order.
     */
    public function view(User $actor, Order $order): bool
    {
        if ($actor->isPlatformStaff()) {
            return true;
        }

        return OrderParticipant::isParticipant($actor, $order);
    }

    /**
     * Only the buyer may open a dispute, and only while the order is funded in escrow (matches {@see \App\Services\Dispute\DisputeService::openDispute} eligibility).
     */
    public function openDisputeAsBuyer(User $actor, Order $order): bool
    {
        if (! OrderParticipant::isBuyer($actor, $order)) {
            return false;
        }

        return $order->status === OrderStatus::PaidInEscrow;
    }
}
