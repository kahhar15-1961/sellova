<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Events\UserNotificationStateChanged;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\Audit\AuditService;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $this->requireUser();
        $role = $this->resolveRole($request);
        $perPage = min(50, max(1, (int) $request->integer('per_page', 8)));

        $notifications = Notification::query()
            ->forPanel((int) $actor->id, $role)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'ok' => true,
            'notifications' => $notifications->getCollection()->map(static fn (Notification $notification): array => NotificationPresenter::present($notification))->values()->all(),
            'unread_count' => Notification::unreadCountForRole((int) $actor->id, $role),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'has_more' => $notifications->hasMorePages(),
            ],
            'role' => $role,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $actor = $this->requireUser();
        $role = $this->resolveRole($request);

        return response()->json([
            'ok' => true,
            'role' => $role,
            'unread_count' => Notification::unreadCountForRole((int) $actor->id, $role),
        ]);
    }

    public function show(Request $request, int $notificationId): JsonResponse
    {
        $notification = $this->ownedNotification($request, $notificationId);

        return response()->json([
            'ok' => true,
            'notification' => NotificationPresenter::present($notification),
        ]);
    }

    public function markRead(Request $request, int $notificationId): JsonResponse
    {
        $notification = $this->ownedNotification($request, $notificationId);
        $role = $this->resolveRole($request);

        if ($notification->read_at === null) {
            $notification->forceFill([
                'read_at' => now(),
                'status' => 'read',
            ])->save();
        }

        $presented = NotificationPresenter::present($notification->fresh() ?? $notification);
        $this->broadcastState((int) $notification->user_id, $role, 'read', [
            'notification' => $presented,
            'notification_id' => (int) $notification->id,
        ]);
        $this->recordAudit('notification.read', $notification, $role);

        return response()->json([
            'ok' => true,
            'notification' => $presented,
            'unread_count' => Notification::unreadCountForRole((int) $notification->user_id, $role),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $actor = $this->requireUser();
        $role = $this->resolveRole($request);
        $query = Notification::query()
            ->forPanel((int) $actor->id, $role)
            ->whereNull('read_at');

        $ids = $query->pluck('id')->all();

        if ($ids !== []) {
            $query->update([
                'read_at' => now(),
                'status' => 'read',
            ]);
        }

        $this->broadcastState((int) $actor->id, $role, 'read_all', [
            'notification_ids' => array_map('intval', $ids),
        ]);
        $this->recordAudit('notification.read_all', null, $role, (int) $actor->id);

        return response()->json([
            'ok' => true,
            'updated' => count($ids),
            'unread_count' => Notification::unreadCountForRole((int) $actor->id, $role),
        ]);
    }

    public function destroy(Request $request, int $notificationId): JsonResponse
    {
        $notification = $this->ownedNotification($request, $notificationId);
        $role = $this->resolveRole($request);
        $userId = (int) $notification->user_id;
        $id = (int) $notification->id;

        $notification->delete();

        $this->broadcastState($userId, $role, 'deleted', [
            'notification_id' => $id,
        ]);
        $this->recordAudit('notification.deleted', $notification, $role);

        return response()->json([
            'ok' => true,
            'deleted' => true,
            'unread_count' => Notification::unreadCountForRole($userId, $role),
        ]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $actor = $this->requireUser();
        $role = $this->resolveRole($request);
        $query = Notification::query()->forPanel((int) $actor->id, $role);
        $ids = $query->pluck('id')->all();

        if ($ids !== []) {
            $query->delete();
        }

        $this->broadcastState((int) $actor->id, $role, 'cleared', [
            'notification_ids' => array_map('intval', $ids),
        ]);
        $this->recordAudit('notification.cleared', null, $role, (int) $actor->id);

        return response()->json([
            'ok' => true,
            'deleted' => count($ids),
            'unread_count' => 0,
        ]);
    }

    private function ownedNotification(Request $request, int $notificationId): Notification
    {
        $actor = $this->requireUser();
        $role = $this->resolveRole($request);

        $notification = Notification::query()
            ->forPanel((int) $actor->id, $role)
            ->whereKey($notificationId)
            ->first();

        abort_if($notification === null, 404);

        return $notification;
    }

    private function resolveRole(Request $request): string
    {
        $role = Notification::normalizeRole((string) $request->query('role', $request->input('role', Notification::ROLE_BUYER)));

        if ($role === Notification::ROLE_SELLER && Auth::user()?->sellerProfile === null) {
            throw new AccessDeniedHttpException('Seller notifications require a seller account.');
        }

        return $role;
    }

    private function requireUser(): \App\Models\User
    {
        $actor = Auth::user();
        abort_if(! $actor instanceof \App\Models\User, 401);

        return $actor;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function broadcastState(int $userId, string $role, string $action, array $extra = []): void
    {
        UserNotificationStateChanged::dispatch($userId, array_merge([
            'user_id' => $userId,
            'role' => $role,
            'action' => $action,
            'unread_count' => Notification::unreadCountForRole($userId, $role),
        ], $extra));
    }

    private function recordAudit(string $action, ?Notification $notification, string $role, ?int $targetId = null): void
    {
        $actor = Auth::user();
        if (! $actor instanceof \App\Models\User) {
            return;
        }

        $this->auditService->record(
            (int) $actor->id,
            $role,
            $action,
            'notification',
            $targetId ?? (int) ($notification?->id ?? 0),
            $notification ? ['status' => (string) $notification->status, 'read_at' => optional($notification->read_at)?->toIso8601String()] : null,
            ['role' => $role],
            $notification?->type ?? $notification?->template_code ?? 'notification',
            (string) Str::uuid(),
            request()->ip(),
        );
    }
}
