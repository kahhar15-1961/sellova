<?php

namespace App\Policies;

use App\Auth\OrderParticipant;
use App\Domain\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    /**
     * Buyer, frozen seller owner, or platform staff may inspect the order.
     */
    public function view(User $actor, Order $order): bool
    {
        if ($actor->isPlatformStaff()) {
            return true;
        }

        return (int) $order->buyer_user_id === (int) $actor->id
            || (int) ($order->seller_user_id ?? 0) === (int) $actor->id;
    }

    /**
     * Only the buyer may open a dispute, and only while the order is funded in escrow (matches {@see \App\Services\Dispute\DisputeService::openDispute} eligibility).
     */
    public function openDisputeAsBuyer(User $actor, Order $order): bool
    {
        if (! OrderParticipant::isBuyer($actor, $order)) {
            return false;
        }

        return in_array($order->status, [
            OrderStatus::PaidInEscrow,
            OrderStatus::EscrowFunded,
            OrderStatus::Processing,
            OrderStatus::DeliverySubmitted,
            OrderStatus::BuyerReview,
        ], true);
    }

    /**
     * Draft → pending_payment: only the buyer or platform staff may advance checkout payment state.
     */
    public function markPendingPayment(User $actor, Order $order): bool
    {
        return $this->buyerOrStaffMayDrivePaymentMutation($actor, $order);
    }

    /**
     * Pending payment → paid_in_escrow: only the buyer or platform staff may apply captured payment.
     */
    public function markPaid(User $actor, Order $order): bool
    {
        return $this->buyerOrStaffMayDrivePaymentMutation($actor, $order);
    }

    private function buyerOrStaffMayDrivePaymentMutation(User $actor, Order $order): bool
    {
        if ($actor->isPlatformStaff()) {
            return true;
        }

        return OrderParticipant::isBuyer($actor, $order);
    }
}
