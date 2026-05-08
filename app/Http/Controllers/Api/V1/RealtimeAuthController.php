<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Exceptions\AuthValidationFailedException;
use App\Http\AppServices;
use App\Http\Responses\ApiEnvelope;
use App\Models\ChatThread;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RealtimeAuthController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function authenticate(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $payload = json_decode($request->getContent(), true);
        $socketId = trim((string) ($payload['socket_id'] ?? ''));
        $channelName = trim((string) ($payload['channel_name'] ?? ''));
        if ($socketId === '' || $channelName === '') {
            throw new AuthValidationFailedException('validation_failed', ['socket_id' => 'required', 'channel_name' => 'required']);
        }

        if (str_starts_with($channelName, 'private-chat.thread.')) {
            $threadId = (int) substr($channelName, strlen('private-chat.thread.'));
            if ($threadId <= 0) {
                throw new AuthValidationFailedException('validation_failed', ['channel_name' => 'invalid_thread']);
            }

            /** @var ChatThread|null $thread */
            $thread = ChatThread::query()->whereKey($threadId)->first();
            if ($thread === null) {
                throw new AuthValidationFailedException('not_found', ['thread_id' => $threadId]);
            }

            $isMember = (int) $thread->buyer_user_id === (int) $actor->id
                || ((int) ($thread->seller_user_id ?? 0) === (int) $actor->id);
            $isSupportStaff = $thread->kind === 'support' && $actor->isPlatformStaff();
            if (! $isMember && ! $isSupportStaff) {
                throw new AuthValidationFailedException('forbidden', ['thread_id' => $threadId]);
            }
        } elseif (str_starts_with($channelName, 'private-App.Models.User.')) {
            $targetUserId = (int) substr($channelName, strlen('private-App.Models.User.'));
            if ($targetUserId <= 0 || $targetUserId !== (int) $actor->id) {
                throw new AuthValidationFailedException('forbidden', ['channel_name' => 'invalid_user_channel']);
            }
        } else {
            throw new AuthValidationFailedException('forbidden', ['channel_name' => 'unsupported']);
        }

        $key = (string) env('REVERB_APP_KEY', 'local-app-key');
        $secret = (string) env('REVERB_APP_SECRET', 'local-app-secret');
        $signature = hash_hmac('sha256', $socketId.':'.$channelName, $secret);

        return ApiEnvelope::data(['auth' => $key.':'.$signature]);
    }
}
