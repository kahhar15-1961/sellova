<?php

namespace App\Services\Escrow;

use App\Domain\Commands\Escrow\ReleaseEscrowCommand;
use App\Domain\Enums\OrderStatus;
use App\Domain\Exceptions\EscrowReleaseConflictException;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Models\Order;

final class EscrowReleaseService
{
    public function __construct(private readonly EscrowService $escrowService = new EscrowService())
    {
    }

    public function releaseAfterBuyerConfirmation(Order $order, int $actorUserId, string $idempotencyKey): array
    {
        if ((int) $order->buyer_user_id !== $actorUserId) {
            throw new OrderValidationFailedException($order->id, 'only_buyer_can_confirm_delivery');
        }
        if (! in_array($order->status, [OrderStatus::BuyerReview, OrderStatus::DeliverySubmitted, OrderStatus::ShippedOrDelivered], true)) {
            throw new EscrowReleaseConflictException((int) ($order->escrowAccount?->id ?? 0), 'buyer_confirmation_not_release_eligible');
        }

        $order->loadMissing('escrowAccount');
        if ($order->escrowAccount === null) {
            throw new OrderValidationFailedException($order->id, 'escrow_account_not_found');
        }

        return $this->escrowService->releaseEscrow(new ReleaseEscrowCommand(
            escrowAccountId: (int) $order->escrowAccount->id,
            idempotencyKey: $idempotencyKey,
        ));
    }
}

