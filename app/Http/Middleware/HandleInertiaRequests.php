<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Admin\AdminAuthorizer;
use App\Models\Notification;
use App\Models\WalletTopUpRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    private function adminNotificationHref(string $templateCode, array $payload): string
    {
        $templateCode = strtolower(trim($templateCode));
        $targetId = (int) (
            $payload['wallet_top_up_request_id']
            ?? $payload['kyc_id']
            ?? $payload['incident_id']
            ?? $payload['target_id']
            ?? $payload['dispute_id']
            ?? $payload['withdrawal_id']
            ?? 0
        );
        $queue = strtolower(trim((string) ($payload['queue'] ?? $payload['queue_code'] ?? '')));

        if ($templateCode === '') {
            return '';
        }

        if (str_starts_with($templateCode, 'admin.wallet_top_up') && $targetId > 0) {
            return route('admin.wallet-top-ups.show', $targetId);
        }
        if (str_starts_with($templateCode, 'admin.kyc') && $targetId > 0) {
            return route('admin.sellers.kyc.show', ['kyc' => $targetId]);
        }
        if (str_starts_with($templateCode, 'admin.sla') || str_starts_with($templateCode, 'admin.escalation')) {
            if ($queue === 'seller_kyc' && $targetId > 0) {
                return route('admin.sellers.kyc.show', ['kyc' => $targetId]);
            }
            if ($queue === 'disputes' && $targetId > 0) {
                return route('admin.disputes.show', ['dispute' => $targetId]);
            }
            if ($queue === 'withdrawals' && $targetId > 0) {
                return route('admin.withdrawals.show', ['withdrawal' => $targetId]);
            }
            if ($targetId > 0) {
                return route('admin.escalations.show', ['incident' => $targetId]);
            }
        }

        return (string) ($payload['href'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->email ?? ('User #'.$user->id),
                ],
            ],
            'admin' => [
                'roles' => $user === null ? [] : AdminAuthorizer::roleCodesForUser($user),
                'permissions' => $user === null ? [] : AdminAuthorizer::permissionCodesForUser($user),
            ],
            'can' => $user === null ? [] : AdminAuthorizer::permissionBooleanMap($user),
            'adminQueueCounts' => $user === null ? [] : [
                'wallet_top_ups' => $user->hasPermissionCode('admin.wallets.view')
                    ? (int) WalletTopUpRequest::query()->where('status', 'requested')->count()
                    : 0,
            ],
            'adminNotifications' => $user === null ? [] : Notification::query()
                ->where('user_id', $user->id)
                ->where('template_code', 'like', 'admin.%')
                ->latest()
                ->limit(8)
                ->get()
                ->map(function (Notification $notification): array {
                    $payload = is_array($notification->payload_json) ? $notification->payload_json : [];

                    return [
                        'id' => (int) $notification->id,
                        'template_code' => (string) ($notification->template_code ?? ''),
                        'title' => (string) ($payload['title'] ?? $notification->template_code ?? 'Notification'),
                        'body' => (string) ($payload['body'] ?? ''),
                        'href' => $this->adminNotificationHref((string) ($notification->template_code ?? ''), $payload),
                        'payload' => $payload,
                        'is_read' => $notification->read_at !== null,
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
            'adminNotificationCount' => $user === null ? 0 : Notification::query()
                ->where('user_id', $user->id)
                ->where('template_code', 'like', 'admin.%')
                ->whereNull('read_at')
                ->count(),
            'filters' => [
                'query' => $request->query(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
