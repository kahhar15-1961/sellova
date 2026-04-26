<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Exceptions\AuthValidationFailedException;
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
                'buyer_user_id' => (int) $order->buyer_user_id,
                'seller_user_id' => (int) $sellerUserId,
                'subject' => 'Order #'.($order->order_number ?? $order->id),
                'status' => 'open',
                'last_message_at' => now(),
            ]
        );

        return ApiEnvelope::data(['thread_id' => (int) $thread->id]);
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
            ->map(function (ChatMessage $m) use ($actor, $counterpartyReadAt): array {
                $fromMe = (int) $m->sender_user_id === (int) $actor->id;
                $deliveryStatus = 'sent';
                if ($fromMe) {
                    if ($counterpartyReadAt !== null && $m->created_at !== null && $m->created_at <= $counterpartyReadAt) {
                        $deliveryStatus = 'read';
                    } elseif ($counterpartyReadAt !== null) {
                        $deliveryStatus = 'delivered';
                    }
                }

                return [
                    'id' => (int) $m->id,
                    'sender_user_id' => (int) $m->sender_user_id,
                    'from_me' => $fromMe,
                    'body' => (string) $m->body,
                    'attachment_url' => $m->attachment_url,
                    'attachment_name' => $m->attachment_name,
                    'delivery_status' => $deliveryStatus,
                    'created_at' => $m->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return ApiEnvelope::data($messages);
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
        if (! $attachment instanceof UploadedFile && $text === '') {
            throw new AuthValidationFailedException('validation_failed', ['body' => 'required_or_attachment']);
        }
        $attachmentUrl = null;
        $attachmentName = null;
        if ($attachment instanceof UploadedFile) {
            $name = Str::uuid()->toString().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $attachment->getClientOriginalName() ?: 'file');
            $targetDir = public_path('uploads/chat');
            if (! is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            $attachment->move($targetDir, $name);
            $attachmentUrl = '/uploads/chat/'.$name;
            $attachmentName = (string) $attachment->getClientOriginalName();
        }

        $message = ChatMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'sender_user_id' => $actor->id,
            'body' => $text,
            'attachment_url' => $attachmentUrl,
            'attachment_name' => $attachmentName,
        ]);

        $thread->last_message_at = now();
        $thread->save();

        ChatThreadRead::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $actor->id],
            ['last_read_at' => now()]
        );

        return ApiEnvelope::data([
            'id' => (int) $message->id,
            'sender_user_id' => (int) $message->sender_user_id,
            'from_me' => true,
            'body' => (string) $message->body,
            'attachment_url' => $message->attachment_url,
            'attachment_name' => $message->attachment_name,
            'delivery_status' => 'sent',
            'created_at' => $message->created_at?->toIso8601String(),
        ], Response::HTTP_CREATED);
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
            throw new AuthValidationFailedException('not_found', ['thread_id' => $threadId]);
        }

        return $thread;
    }

    private function sellerUserForOrder(int $orderId): ?int
    {
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
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('buyer_user_id');
                $table->unsignedBigInteger('seller_user_id')->nullable();
                $table->string('subject', 191)->nullable();
                $table->string('status', 32)->default('open');
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 191)->nullable();
                $table->unsignedBigInteger('thread_id');
                $table->unsignedBigInteger('sender_user_id');
                $table->text('body');
                $table->string('attachment_url', 512)->nullable();
                $table->string('attachment_name', 191)->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('chat_messages', 'attachment_url')) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->string('attachment_url', 512)->nullable()->after('body');
                $table->string('attachment_name', 191)->nullable()->after('attachment_url');
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

