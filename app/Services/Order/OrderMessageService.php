<?php

namespace App\Services\Order;

use App\Domain\Enums\ProductType;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Events\ChatMessageCreated;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\ChatThreadRead;
use App\Models\Order;
use App\Models\OrderMessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class OrderMessageService
{
    public function getOrCreateEscrowThread(Order $order): ChatThread
    {
        $sellerUserId = (int) ($order->seller_user_id ?? 0);
        if ($sellerUserId <= 0) {
            throw new OrderValidationFailedException($order->id, 'order_seller_not_found');
        }

        return ChatThread::query()->firstOrCreate(
            ['kind' => 'order', 'order_id' => $order->id, 'purpose' => 'escrow'],
            [
                'uuid' => (string) Str::uuid(),
                'buyer_user_id' => (int) $order->buyer_user_id,
                'seller_user_id' => $sellerUserId,
                'subject' => 'Escrow order #'.($order->order_number ?? $order->id),
                'status' => 'open',
                'last_message_at' => now(),
            ],
        );
    }

    public function ensureParticipant(Order $order, int $userId, bool $allowAdmin = false): string
    {
        if ((int) $order->buyer_user_id === $userId) {
            return 'buyer';
        }

        if ((int) ($order->seller_user_id ?? 0) === $userId) {
            return 'seller';
        }

        if ($allowAdmin) {
            return 'admin';
        }

        throw new OrderValidationFailedException($order->id, 'order_access_denied');
    }

    public function listMessages(Order $order, int $viewerUserId, bool $allowAdmin = false): array
    {
        $thread = $this->getOrCreateEscrowThread($order);
        $this->ensureParticipant($order, $viewerUserId, $allowAdmin);

        $counterpartyUserId = $this->counterpartyUserId($thread, $viewerUserId);
        $counterpartyReadAt = null;
        if ($counterpartyUserId !== null) {
            $counterpartyReadAt = ChatThreadRead::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $counterpartyUserId)
                ->value('last_read_at');
        }

        return ChatMessage::query()
            ->with('escrowAttachments')
            ->where('thread_id', $thread->id)
            ->orderBy('id')
            ->get()
            ->map(function (ChatMessage $message) use ($viewerUserId, $counterpartyReadAt): array {
                $fromMe = (int) $message->sender_user_id === $viewerUserId;
                $deliveryStatus = 'sent';
                if ($fromMe) {
                    if ($counterpartyReadAt !== null && $message->created_at !== null && $message->created_at <= $counterpartyReadAt) {
                        $deliveryStatus = 'read';
                    } elseif ($counterpartyReadAt !== null) {
                        $deliveryStatus = 'delivered';
                    }
                }

                return $this->messagePayload($message, $fromMe, $deliveryStatus);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function sendMessage(
        Order $order,
        int $senderUserId,
        string $body,
        array $attachments = [],
        ?string $artifactType = null,
        bool $isDeliveryProof = false,
        bool $allowAdmin = false,
        ?string $senderRoleOverride = null,
    ): array {
        $thread = $this->getOrCreateEscrowThread($order);
        $senderRole = $senderRoleOverride ?? $this->ensureParticipant($order, $senderUserId, $allowAdmin);
        $body = trim($body);

        if ($body === '' && $attachments === []) {
            throw new OrderValidationFailedException($order->id, 'message_or_attachment_required');
        }

        $message = ChatMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'sender_user_id' => $senderUserId,
            'receiver_user_id' => $this->counterpartyUserId($thread, $senderUserId),
            'sender_role' => $senderRole,
            'body' => $body,
            'marker_type' => $senderRole === 'admin' ? 'system_notice' : null,
            'artifact_type' => $artifactType,
            'is_delivery_proof' => $isDeliveryProof,
        ]);

        foreach ($attachments as $attachment) {
            $this->storeAttachment($order, $message, $senderUserId, $attachment);
        }

        $thread->last_message_at = now();
        $thread->status = $this->isThreadLocked($order) ? 'closed' : 'open';
        $thread->save();

        ChatThreadRead::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $senderUserId],
            ['last_read_at' => now()],
        );

        $message->load('escrowAttachments');
        $payload = $this->messagePayload($message, true, 'sent');
        ChatMessageCreated::dispatch((int) $thread->id, $payload);

        return $payload;
    }

    public function sendSystemNotice(Order $order, string $body, ?string $artifactType = 'system_notice'): array
    {
        return $this->sendMessage(
            order: $order,
            senderUserId: (int) ($order->seller_user_id ?? $order->buyer_user_id),
            body: $body,
            attachments: [],
            artifactType: $artifactType,
            isDeliveryProof: false,
            allowAdmin: true,
            senderRoleOverride: 'admin',
        );
    }

    public function markRead(Order $order, int $viewerUserId, bool $allowAdmin = false): void
    {
        $thread = $this->getOrCreateEscrowThread($order);
        $this->ensureParticipant($order, $viewerUserId, $allowAdmin);

        ChatThreadRead::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $viewerUserId],
            ['last_read_at' => now()],
        );
    }

    public function threadId(Order $order): int
    {
        return (int) $this->getOrCreateEscrowThread($order)->id;
    }

    public function isThreadLocked(Order $order): bool
    {
        return in_array((string) $order->status->value, ['completed', 'cancelled', 'refunded'], true);
    }

    private function counterpartyUserId(ChatThread $thread, int $viewerUserId): ?int
    {
        if ((int) $thread->buyer_user_id === $viewerUserId) {
            return $thread->seller_user_id !== null ? (int) $thread->seller_user_id : null;
        }

        if ($thread->seller_user_id !== null && (int) $thread->seller_user_id === $viewerUserId) {
            return (int) $thread->buyer_user_id;
        }

        return (int) $thread->buyer_user_id ?: ($thread->seller_user_id !== null ? (int) $thread->seller_user_id : null);
    }

    private function storeAttachment(Order $order, ChatMessage $message, int $senderUserId, UploadedFile $attachment): void
    {
        if (! $attachment->isValid()) {
            throw new OrderValidationFailedException($order->id, 'attachment_upload_failed');
        }

        $size = (int) ($attachment->getSize() ?? 0);
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            throw new OrderValidationFailedException($order->id, 'attachment_size_invalid');
        }

        $mime = (string) ($attachment->getMimeType() ?? 'application/octet-stream');
        $kind = str_starts_with($mime, 'image/') ? 'image' : 'file';
        $extension = strtolower((string) $attachment->getClientOriginalExtension());
        $storedName = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $path = $attachment->storeAs('private/escrow-chat/'.$order->id, $storedName, 'local');

        OrderMessageAttachment::query()->create([
            'uuid' => (string) Str::uuid(),
            'chat_message_id' => (int) $message->id,
            'order_id' => (int) $order->id,
            'uploaded_by_user_id' => $senderUserId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => (string) ($attachment->getClientOriginalName() ?: $storedName),
            'mime_type' => $mime,
            'size_bytes' => $size,
            'attachment_kind' => $kind,
            'visibility' => 'escrow',
            'scan_status' => 'pending',
        ]);
    }

    private function messagePayload(ChatMessage $message, bool $fromMe, string $deliveryStatus): array
    {
        return [
            'id' => (int) $message->id,
            'sender_user_id' => (int) $message->sender_user_id,
            'receiver_user_id' => $message->receiver_user_id !== null ? (int) $message->receiver_user_id : null,
            'sender_role' => (string) ($message->sender_role ?? 'buyer'),
            'from_me' => $fromMe,
            'body' => (string) ($message->body ?? ''),
            'marker_type' => $message->marker_type,
            'artifact_type' => $message->artifact_type,
            'is_delivery_proof' => (bool) $message->is_delivery_proof,
            'delivery_status' => $deliveryStatus,
            'created_at' => $message->created_at?->toIso8601String(),
            'attachments' => $message->escrowAttachments
                ->map(static fn (OrderMessageAttachment $attachment): array => [
                    'id' => (int) $attachment->id,
                    'name' => (string) $attachment->original_name,
                    'mime_type' => (string) ($attachment->mime_type ?? ''),
                    'size_bytes' => (int) $attachment->size_bytes,
                    'kind' => (string) ($attachment->attachment_kind ?? 'file'),
                    'download_url' => URL::temporarySignedRoute(
                        'web.actions.orders.messages.attachments.download',
                        now()->addMinutes(20),
                        ['orderMessageAttachment' => $attachment->id],
                    ),
                ])
                ->values()
                ->all(),
        ];
    }
}
