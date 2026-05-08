<?php

namespace App\Services\Delivery;

use App\Domain\Enums\ProductType;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Order;
use Illuminate\Support\Str;

final class DeliveryChatService
{
    public function getOrCreateProofThread(Order $order): ChatThread
    {
        $type = ProductType::normalize($order->product_type);
        if (! $type->requiresDeliveryChat()) {
            throw new OrderValidationFailedException($order->id, 'delivery_chat_not_required_for_physical_order');
        }

        $sellerUserId = (int) $order->seller_user_id;
        if ($sellerUserId <= 0) {
            throw new OrderValidationFailedException($order->id, 'order_seller_not_frozen');
        }

        return ChatThread::query()->firstOrCreate(
            ['kind' => 'order', 'order_id' => $order->id, 'purpose' => 'delivery_proof'],
            [
                'uuid' => (string) Str::uuid(),
                'buyer_user_id' => (int) $order->buyer_user_id,
                'seller_user_id' => $sellerUserId,
                'subject' => 'Delivery proof for #'.($order->order_number ?? $order->id),
                'status' => 'open',
                'last_message_at' => now(),
            ],
        );
    }

    public function addMarker(Order $order, int $actorUserId, string $markerType, ?string $note = null, ?string $artifactType = null): ChatMessage
    {
        $thread = $this->getOrCreateProofThread($order);
        $message = ChatMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'sender_user_id' => $actorUserId,
            'receiver_user_id' => (int) $thread->buyer_user_id === $actorUserId ? $thread->seller_user_id : $thread->buyer_user_id,
            'sender_role' => (int) $thread->seller_user_id === $actorUserId ? 'seller' : 'buyer',
            'body' => trim((string) ($note ?? '')),
            'marker_type' => $markerType,
            'artifact_type' => $artifactType,
            'is_delivery_proof' => in_array($markerType, ['delivery_submitted', 'instant_delivery_logged', 'service_completed'], true),
        ]);

        $thread->last_message_at = now();
        $thread->save();

        return $message;
    }
}
