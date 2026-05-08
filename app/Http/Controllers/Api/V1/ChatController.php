<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Exceptions\AuthValidationFailedException;
use App\Events\ChatMessageCreated;
use App\Events\ChatTypingUpdated;
use App\Http\AppServices;
use App\Http\Responses\ApiEnvelope;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\ChatThreadRead;
use App\Models\Order;
use App\Models\SellerProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ChatController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function listThreads(Request $request): Response
    {
        $this->ensureChatTables();
        $actor = $this->app->requireActor($request);

        $threads = ChatThread::query()
            ->where(function ($q) use ($actor): void {
                $q->where('buyer_user_id', $actor->id)
                    ->orWhere('seller_user_id', $actor->id);
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $items = $threads->map(function (ChatThread $thread) use ($actor): array {
            $read = ChatThreadRead::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $actor->id)
                ->first();
            $lastMessage = ChatMessage::query()
                ->where('thread_id', $thread->id)
                ->orderByDesc('id')
                ->first();
            $hasUnread = false;
            if ($lastMessage !== null) {
                $lastReadAt = $read?->last_read_at;
                $hasUnread = $lastMessage->sender_user_id !== (int) $actor->id
                    && ($lastReadAt === null || $lastMessage->created_at?->gt($lastReadAt) === true);
            }

            return [
                'id' => (int) $thread->id,
                'kind' => (string) $thread->kind,
                'purpose' => (string) ($thread->purpose ?? 'conversation'),
                'order_id' => $thread->order_id,
                'subject' => (string) ($thread->subject ?? 'Chat'),
                'status' => (string) $thread->status,
                'has_unread' => $hasUnread,
                'last_message_preview' => $lastMessage?->body ?? '',
                'last_message_at' => $thread->last_message_at?->toIso8601String(),
                'counterparty_label' => $this->counterpartyLabel($thread, (int) $actor->id),
            ];
        })->values()->all();

        $unreadCount = 0;
        foreach ($items as $item) {
            if (($item['has_unread'] ?? false) === true) {
                $unreadCount++;
            }
        }

        return ApiEnvelope::data([
            'items' => $items,
            'unread_count' => $unreadCount,
        ]);
    }

    public function getOrCreateOrderThread(Request $request): Response
    {
        $this->ensureChatTables();
        $actor = $this->app->requireActor($request);
        $orderId = (int) $request->attributes->get('orderId');

        /** @var Order|null $order */
        $order = Order::query()->whereKey($orderId)->first();
        if ($order === null) {
            throw new AuthValidationFailedException('not_found', ['order_id' => $orderId]);
        }

        $sellerUserId = $this->sellerUserForOrder($orderId);
        if ($sellerUserId === null) {
            throw new AuthValidationFailedException('seller_profile_not_found', ['order_id' => $orderId]);
        }

        $isBuyer = (int) $order->buyer_user_id === (int) $actor->id;
        $isSeller = (int) $sellerUserId === (int) $actor->id;
        if (! $isBuyer && ! $isSeller && ! $actor->isPlatformStaff()) {
            throw new AuthValidationFailedException('not_found', ['order_id' => $orderId]);
        }

        /** @var ChatThread $thread */
        $thread = ChatThread::query()->firstOrCreate(
            ['kind' => 'order', 'order_id' => $orderId],
            [
                'uuid' => (string) Str::uuid(),
                'purpose' => 'conversation',
                'buyer_user_id' => (int) $order->buyer_user_id,
                'seller_user_id' => (int) $sellerUserId,
                'subject' => 'Order #'.($order->order_number ?? $order->id),
                'status' => 'open',
                'last_message_at' => now(),
            ]
        );
        $this->syncOrderThreadParticipants($thread, $order, (int) $sellerUserId);

        if ($isBuyer) {
            $this->seedBuyerOrderSummaryMessage($thread, $order, (int) $actor->id, (int) $sellerUserId, $request);
        }

        return ApiEnvelope::data(['thread_id' => (int) $thread->id]);
    }

    private function seedBuyerOrderSummaryMessage(
        ChatThread $thread,
        Order $order,
        int $buyerUserId,
        int $sellerUserId,
        Request $request
    ): void {
        $exists = ChatMessage::query()
            ->where('thread_id', $thread->id)
            ->where('marker_type', 'buyer_order_summary')
            ->exists();
        if ($exists) {
            return;
        }

        $order->loadMissing('orderItems');
        $orderNumber = (string) ($order->order_number ?? ('ORD-'.$order->id));
        $currency = strtoupper((string) ($order->currency ?? ''));
        $total = trim($currency.' '.number_format((float) $order->net_amount, 2));
        $productType = str_replace('_', ' ', (string) ($order->product_type ?? 'order'));
        $lines = $order->orderItems
            ->map(static function ($item): string {
                $title = trim((string) ($item->title_snapshot ?? 'Item'));
                $qty = (int) ($item->quantity ?? 1);
                $lineTotal = number_format((float) $item->line_total_snapshot, 2);

                return "- {$title} x {$qty} ({$lineTotal})";
            })
            ->values()
            ->all();

        $itemSummary = $lines === []
            ? '- Order item details unavailable'
            : implode("\n", $lines);

        $body = implode("\n", [
            "Hi, I just placed order #{$orderNumber}.",
            'Please deliver it as soon as possible when escrow is ready.',
            '',
            "Type: {$productType}",
            "Total: {$total}",
            $itemSummary,
        ]);

        $message = ChatMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'sender_user_id' => $buyerUserId,
            'receiver_user_id' => $sellerUserId,
            'sender_role' => 'buyer',
            'body' => $body,
            'marker_type' => 'buyer_order_summary',
            'artifact_type' => 'order_summary',
            'is_delivery_proof' => false,
        ]);

        $thread->last_message_at = now();
        $thread->save();

        ChatThreadRead::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $buyerUserId],
            ['last_read_at' => now()]
        );

        event(new ChatMessageCreated(
            threadId: (int) $thread->id,
            message: [
                ...$this->messagePayload($message, false, 'sent', $request),
            ],
        ));
    }

    public function listMessages(Request $request): Response
    {
        $this->ensureChatTables();
        $actor = $this->app->requireActor($request);
        $thread = $this->authorizedThread((int) $actor->id, (int) $request->attributes->get('threadId'));
        $counterpartyUserId = $this->counterpartyUserId($thread, (int) $actor->id);
        $counterpartyReadAt = null;
        if ($counterpartyUserId !== null) {
            $counterpartyReadAt = ChatThreadRead::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $counterpartyUserId)
                ->value('last_read_at');
        }

        $messages = ChatMessage::query()
            ->where('thread_id', $thread->id)
            ->orderBy('id')
            ->get()
            ->map(function (ChatMessage $m) use ($actor, $counterpartyReadAt, $request): array {
                $fromMe = (int) $m->sender_user_id === (int) $actor->id;
                $deliveryStatus = 'sent';
                if ($fromMe) {
                    if ($counterpartyReadAt !== null && $m->created_at !== null && $m->created_at <= $counterpartyReadAt) {
                        $deliveryStatus = 'read';
                    } elseif ($counterpartyReadAt !== null) {
                        $deliveryStatus = 'delivered';
                    }
                }

                return $this->messagePayload($m, $fromMe, $deliveryStatus, $request);
            })
            ->values()
            ->all();

        return ApiEnvelope::data($messages);
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(ChatMessage $m, bool $fromMe, string $deliveryStatus, Request $request): array
    {
        $attachmentUrl = $m->attachment_url;
        $attachmentDataUrl = null;
        $attachmentMime = $m->attachment_mime;
        if ((string) $m->attachment_type === 'image'
            && is_string($m->attachment_url)
            && $m->attachment_url !== ''
            && str_starts_with($m->attachment_url, '/')
            && is_string($attachmentMime)
            && $attachmentMime !== '') {
            $localPath = public_path(ltrim($m->attachment_url, '/'));
            if (is_file($localPath) && filesize($localPath) <= 10 * 1024 * 1024) {
                $contents = file_get_contents($localPath);
                if ($contents !== false) {
                    $attachmentDataUrl = 'data:'.$attachmentMime.';base64,'.base64_encode($contents);
                }
            }
        }
        if (is_string($attachmentUrl) && $attachmentUrl !== '' && str_starts_with($attachmentUrl, '/')) {
            $attachmentUrl = rtrim($request->getSchemeAndHttpHost(), '/').$attachmentUrl;
        }

        return [
            'id' => (int) $m->id,
            'sender_user_id' => (int) $m->sender_user_id,
            'receiver_user_id' => $m->receiver_user_id !== null ? (int) $m->receiver_user_id : null,
            'sender_role' => $m->sender_role,
            'from_me' => $fromMe,
            'body' => (string) $m->body,
            'marker_type' => $m->marker_type,
            'artifact_type' => $m->artifact_type,
            'is_delivery_proof' => (bool) $m->is_delivery_proof,
            'attachment_url' => $attachmentUrl,
            'attachment_name' => $m->attachment_name,
            'attachment_type' => $m->attachment_type,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $m->attachment_size !== null ? (int) $m->attachment_size : null,
            'attachment_data_url' => $attachmentDataUrl,
            'delivery_status' => $deliveryStatus,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    public function setTyping(Request $request): Response
    {
        $this->ensureChatTables();
        $actor = $this->app->requireActor($request);
        $threadId = (int) $request->attributes->get('threadId');
        $this->authorizedThread((int) $actor->id, $threadId);
        $body = json_decode($request->getContent(), true);
        $typing = (bool) (($body['typing'] ?? false) === true);
        $key = $this->typingCacheKey($threadId, (int) $actor->id);
        if ($typing) {
            Cache::put($key, [
                'user_id' => (int) $actor->id,
                'name' => (string) ($actor->email ?? ('User #'.$actor->id)),
            ], now()->addSeconds(8));
        } else {
            Cache::forget($key);
        }

        event(new ChatTypingUpdated(
            threadId: $threadId,
            userId: (int) $actor->id,
            name: (string) ($actor->email ?? ('User #'.$actor->id)),
            typing: $typing,
        ));

        return ApiEnvelope::data(['ok' => true]);
    }

    public function typingStatus(Request $request): Response
    {
        $this->ensureChatTables();
        $actor = $this->app->requireActor($request);
        $threadId = (int) $request->attributes->get('threadId');
        $thread = $this->authorizedThread((int) $actor->id, $threadId);

        $userIds = array_values(array_filter([
            (int) $thread->buyer_user_id,
            $thread->seller_user_id !== null ? (int) $thread->seller_user_id : null,
        ], static fn ($id): bool => $id !== null && (int) $id > 0 && (int) $id !== (int) $actor->id));

        $typingUsers = [];
        foreach ($userIds as $uid) {
            $data = Cache::get($this->typingCacheKey($threadId, $uid));
            if (is_array($data)) {
                $typingUsers[] = [
                    'user_id' => (int) ($data['user_id'] ?? $uid),
                    'name' => (string) ($data['name'] ?? ('User #'.$uid)),
                ];
            }
        }

        return ApiEnvelope::data($typingUsers);
    }

    public function sendMessage(Request $request): Response
    {
        $this->ensureChatTables();
        $actor = $this->app->requireActor($request);
        $thread = $this->authorizedThread((int) $actor->id, (int) $request->attributes->get('threadId'));
        $body = $request->request->all();
        if ($body === [] && str_contains((string) $request->headers->get('content-type'), 'application/json')) {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        $text = trim((string) (($body['body'] ?? '') ?: ''));
        $attachment = $request->files->get('attachment');
        $encodedAttachment = isset($body['attachment_base64']) ? trim((string) $body['attachment_base64']) : '';
        if (! $attachment instanceof UploadedFile && $encodedAttachment === '' && $text === '') {
            throw new AuthValidationFailedException('validation_failed', ['body' => 'required_or_attachment']);
        }
        $attachmentUrl = null;
        $attachmentName = null;
        $attachmentType = null;
        $attachmentMime = null;
        $attachmentSize = null;
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $documentExtensions = ['pdf', 'txt', 'doc', 'docx', 'zip'];
        $allowedExtensions = array_merge($imageExtensions, $documentExtensions);
        $allowed = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ];
        if ($attachment instanceof UploadedFile) {
            if (! $attachment->isValid()) {
                throw new AuthValidationFailedException('validation_failed', ['attachment' => 'upload_failed']);
            }
            $attachmentSize = (int) ($attachment->getSize() ?? 0);
            if ($attachmentSize <= 0 || $attachmentSize > 10 * 1024 * 1024) {
                throw new AuthValidationFailedException('validation_failed', ['attachment' => 'max_10mb']);
            }
            $originalName = (string) ($attachment->getClientOriginalName() ?: 'file');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $attachmentMime = (string) ($attachment->getMimeType() ?: 'application/octet-stream');
            if (! in_array($attachmentMime, $allowed, true) && ! in_array($extension, $allowedExtensions, true)) {
                throw new AuthValidationFailedException('validation_failed', ['attachment' => 'unsupported_file_type']);
            }
            $attachmentType = str_starts_with($attachmentMime, 'image/') || in_array($extension, $imageExtensions, true)
                ? 'image'
                : ($attachmentMime === 'application/pdf' || $extension === 'pdf' ? 'document' : 'file');
            $name = Str::uuid()->toString().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $targetDir = public_path('uploads/chat');
            if (! is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            $attachment->move($targetDir, $name);
            $attachmentUrl = '/uploads/chat/'.$name;
            $attachmentName = $originalName;
        } elseif ($encodedAttachment !== '') {
            $originalName = (string) (($body['attachment_name'] ?? '') ?: 'file');
            $originalName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', $originalName) ?: 'file';
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $attachmentMime = (string) (($body['attachment_mime'] ?? '') ?: 'application/octet-stream');
            if (str_starts_with($encodedAttachment, 'data:')) {
                [, $encodedAttachment] = explode(',', $encodedAttachment, 2) + [1 => ''];
            }
            $decoded = base64_decode($encodedAttachment, true);
            if ($decoded === false) {
                throw new AuthValidationFailedException('validation_failed', ['attachment' => 'invalid_file_data']);
            }
            $attachmentSize = strlen($decoded);
            if ($attachmentSize <= 0 || $attachmentSize > 10 * 1024 * 1024) {
                throw new AuthValidationFailedException('validation_failed', ['attachment' => 'max_10mb']);
            }
            if (! in_array($attachmentMime, $allowed, true) && ! in_array($extension, $allowedExtensions, true)) {
                throw new AuthValidationFailedException('validation_failed', ['attachment' => 'unsupported_file_type']);
            }
            $attachmentType = str_starts_with($attachmentMime, 'image/') || in_array($extension, $imageExtensions, true)
                ? 'image'
                : ($attachmentMime === 'application/pdf' || $extension === 'pdf' ? 'document' : 'file');
            $name = Str::uuid()->toString().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $targetDir = public_path('uploads/chat');
            if (! is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            file_put_contents($targetDir.DIRECTORY_SEPARATOR.$name, $decoded);
            $attachmentUrl = '/uploads/chat/'.$name;
            $attachmentName = $originalName;
        }
        $counterpartyUserId = $this->counterpartyUserId($thread, (int) $actor->id);
        $senderRole = (int) $thread->seller_user_id === (int) $actor->id
            ? 'seller'
            : ((int) $thread->buyer_user_id === (int) $actor->id ? 'buyer' : 'admin');
        $isDeliveryProof = filter_var($body['is_delivery_proof'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $artifactType = isset($body['artifact_type']) ? trim((string) $body['artifact_type']) : null;
        if ($artifactType === '') {
            $artifactType = null;
        }

        $message = ChatMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'sender_user_id' => $actor->id,
            'receiver_user_id' => $counterpartyUserId,
            'sender_role' => $senderRole,
            'body' => $text,
            'artifact_type' => $artifactType,
            'is_delivery_proof' => $isDeliveryProof,
            'attachment_url' => $attachmentUrl,
            'attachment_name' => $attachmentName,
            'attachment_type' => $attachmentType,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
        ]);

        $thread->last_message_at = now();
        $thread->save();

        ChatThreadRead::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $actor->id],
            ['last_read_at' => now()]
        );

        event(new ChatMessageCreated(
            threadId: (int) $thread->id,
            message: [
                ...$this->messagePayload($message, false, 'sent', $request),
            ],
        ));

        return ApiEnvelope::data($this->messagePayload($message, true, 'sent', $request), Response::HTTP_CREATED);
    }

    public function markThreadRead(Request $request): Response
    {
        $this->ensureChatTables();
        $actor = $this->app->requireActor($request);
        $thread = $this->authorizedThread((int) $actor->id, (int) $request->attributes->get('threadId'));

        ChatThreadRead::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $actor->id],
            ['last_read_at' => now()]
        );

        return ApiEnvelope::data(['ok' => true]);
    }

    public function createSupportTicket(Request $request): Response
    {
        $this->ensureChatTables();
        $actor = $this->app->requireActor($request);
        $body = json_decode($request->getContent(), true);
        $subject = trim((string) (($body['subject'] ?? '') ?: ''));
        $message = trim((string) (($body['message'] ?? '') ?: ''));
        if ($subject === '' || $message === '') {
            throw new AuthValidationFailedException('validation_failed', [
                'subject' => $subject === '' ? 'required' : null,
                'message' => $message === '' ? 'required' : null,
            ]);
        }

        $thread = ChatThread::query()->create([
            'uuid' => (string) Str::uuid(),
            'kind' => 'support',
            'purpose' => 'conversation',
            'order_id' => null,
            'buyer_user_id' => (int) $actor->id,
            'seller_user_id' => null,
            'subject' => $subject,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        ChatMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'sender_user_id' => $actor->id,
            'body' => $message,
        ]);
        ChatThreadRead::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $actor->id],
            ['last_read_at' => now()]
        );

        return ApiEnvelope::data(['thread_id' => (int) $thread->id], Response::HTTP_CREATED);
    }

    public function listSupportInbox(Request $request): Response
    {
        $this->ensureChatTables();
        $actor = $this->app->requireActor($request);
        if (! $actor->isPlatformStaff()) {
            throw new AuthValidationFailedException('forbidden', ['action' => 'support_inbox']);
        }

        $threads = ChatThread::query()
            ->where('kind', 'support')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (ChatThread $thread): array {
                $lastMessage = ChatMessage::query()
                    ->where('thread_id', $thread->id)
                    ->orderByDesc('id')
                    ->first();

                return [
                    'id' => (int) $thread->id,
                    'subject' => (string) ($thread->subject ?? 'Support'),
                    'status' => (string) $thread->status,
                    'buyer_user_id' => (int) $thread->buyer_user_id,
                    'last_message_preview' => $lastMessage?->body ?? '',
                    'last_message_at' => $thread->last_message_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return ApiEnvelope::data($threads);
    }

    private function authorizedThread(int $actorUserId, int $threadId): ChatThread
    {
        $actor = \App\Models\User::query()->find($actorUserId);
        if ($actor !== null && $actor->isPlatformStaff()) {
            /** @var ChatThread|null $staffThread */
            $staffThread = ChatThread::query()
                ->whereKey($threadId)
                ->first();
            if ($staffThread !== null && $staffThread->kind === 'support') {
                return $staffThread;
            }
        }

        /** @var ChatThread|null $thread */
        $thread = ChatThread::query()
            ->whereKey($threadId)
            ->where(function ($q) use ($actorUserId): void {
                $q->where('buyer_user_id', $actorUserId)
                    ->orWhere('seller_user_id', $actorUserId);
            })
            ->first();
        if ($thread === null) {
            /** @var ChatThread|null $candidate */
            $candidate = ChatThread::query()->whereKey($threadId)->first();
            if ($candidate !== null && $candidate->kind === 'order' && $candidate->order_id !== null) {
                /** @var Order|null $order */
                $order = Order::query()->whereKey((int) $candidate->order_id)->first();
                $sellerUserId = $this->sellerUserForOrder((int) $candidate->order_id);
                if ($order !== null
                    && $sellerUserId !== null
                    && ((int) $order->buyer_user_id === $actorUserId || (int) $sellerUserId === $actorUserId)) {
                    $this->syncOrderThreadParticipants($candidate, $order, (int) $sellerUserId);

                    return $candidate->fresh() ?? $candidate;
                }
            }
            throw new AuthValidationFailedException('not_found', ['thread_id' => $threadId]);
        }

        return $thread;
    }

    private function syncOrderThreadParticipants(ChatThread $thread, Order $order, int $sellerUserId): void
    {
        $changed = false;
        if ((int) $thread->buyer_user_id !== (int) $order->buyer_user_id) {
            $thread->buyer_user_id = (int) $order->buyer_user_id;
            $changed = true;
        }
        if ((int) ($thread->seller_user_id ?? 0) !== $sellerUserId) {
            $thread->seller_user_id = $sellerUserId;
            $changed = true;
        }
        $expectedSubject = 'Order #'.($order->order_number ?? $order->id);
        if ((string) $thread->subject !== $expectedSubject) {
            $thread->subject = $expectedSubject;
            $changed = true;
        }
        if ($changed) {
            $thread->save();
        }
    }

    private function sellerUserForOrder(int $orderId): ?int
    {
        $frozenSellerId = Order::query()->whereKey($orderId)->value('seller_user_id');
        if ($frozenSellerId !== null && (int) $frozenSellerId > 0) {
            return (int) $frozenSellerId;
        }

        $sellerProfileId = \App\Models\OrderItem::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->value('seller_profile_id');
        if ($sellerProfileId === null) {
            return null;
        }
        /** @var SellerProfile|null $seller */
        $seller = SellerProfile::query()->whereKey((int) $sellerProfileId)->first();

        return $seller?->user_id !== null ? (int) $seller->user_id : null;
    }

    private function counterpartyLabel(ChatThread $thread, int $actorUserId): string
    {
        if ($thread->kind === 'support') {
            return 'Sellova Support';
        }
        if ((int) $thread->buyer_user_id === $actorUserId) {
            return 'Seller';
        }

        return 'Buyer';
    }

    private function typingCacheKey(int $threadId, int $userId): string
    {
        return 'chat:typing:thread:'.$threadId.':user:'.$userId;
    }

    private function counterpartyUserId(ChatThread $thread, int $actorUserId): ?int
    {
        if ((int) $thread->buyer_user_id === $actorUserId) {
            return $thread->seller_user_id !== null ? (int) $thread->seller_user_id : null;
        }
        if ($thread->seller_user_id !== null && (int) $thread->seller_user_id === $actorUserId) {
            return (int) $thread->buyer_user_id;
        }

        return null;
    }

    private function ensureChatTables(): void
    {
        if (! Schema::hasTable('chat_threads')) {
            Schema::create('chat_threads', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->nullable();
                $table->string('kind', 32)->default('order');
                $table->string('purpose', 32)->default('conversation');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('buyer_user_id');
                $table->unsignedBigInteger('seller_user_id')->nullable();
                $table->string('subject', 191)->nullable();
                $table->string('status', 32)->default('open');
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
            });
        }
        if (Schema::hasTable('chat_threads') && ! Schema::hasColumn('chat_threads', 'purpose')) {
            Schema::table('chat_threads', function (Blueprint $table): void {
                $table->string('purpose', 32)->default('conversation')->after('kind');
            });
        }
        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->nullable();
                $table->unsignedBigInteger('thread_id');
                $table->unsignedBigInteger('sender_user_id');
                $table->unsignedBigInteger('receiver_user_id')->nullable();
                $table->string('sender_role', 32)->nullable();
                $table->text('body');
                $table->string('marker_type', 64)->nullable();
                $table->string('artifact_type', 64)->nullable();
                $table->boolean('is_delivery_proof')->default(false);
                $table->string('attachment_url', 512)->nullable();
                $table->string('attachment_name', 191)->nullable();
                $table->string('attachment_type', 32)->nullable();
                $table->string('attachment_mime', 191)->nullable();
                $table->unsignedBigInteger('attachment_size')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('chat_messages', 'attachment_url')) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->string('attachment_url', 512)->nullable()->after('body');
                $table->string('attachment_name', 191)->nullable()->after('attachment_url');
            });
        }
        if (Schema::hasTable('chat_messages') && ! Schema::hasColumn('chat_messages', 'marker_type')) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->string('marker_type', 64)->nullable()->after('body');
                $table->string('artifact_type', 64)->nullable()->after('marker_type');
                $table->boolean('is_delivery_proof')->default(false)->after('artifact_type');
            });
        }
        if (Schema::hasTable('chat_messages') && ! Schema::hasColumn('chat_messages', 'receiver_user_id')) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->unsignedBigInteger('receiver_user_id')->nullable()->after('sender_user_id');
            });
        }
        if (Schema::hasTable('chat_messages') && ! Schema::hasColumn('chat_messages', 'sender_role')) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->string('sender_role', 32)->nullable()->after('receiver_user_id');
            });
        }
        if (Schema::hasTable('chat_messages') && ! Schema::hasColumn('chat_messages', 'attachment_type')) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->string('attachment_type', 32)->nullable()->after('attachment_name');
                $table->string('attachment_mime', 191)->nullable()->after('attachment_type');
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            });
        }
        if (! Schema::hasTable('chat_thread_reads')) {
            Schema::create('chat_thread_reads', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('thread_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();
            });
        }
    }
}
