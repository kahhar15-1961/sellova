<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Domain\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use App\Services\Admin\AdminListsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class OrdersController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->lists->ordersIndex($request, $user);

        return Inertia::render('Admin/Orders/Index', [
            'header' => $this->pageHeader(
                'Orders',
                'Fulfillment pipeline: live orders from the database with search and status filters.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Orders & Escrow'],
                    ['label' => 'Orders'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.orders.index'),
            'status_options' => collect(OrderStatus::cases())->map(static fn (OrderStatus $s): array => [
                'value' => $s->value,
                'label' => ucwords(str_replace('_', ' ', $s->value)),
            ])->values()->all(),
        ]);
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();
        if ($actor === null || (! $actor->isPlatformStaff() && ! $actor->hasPermissionCode(AdminPermission::ORDERS_MANAGE))) {
            abort(403);
        }

        $before = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'buyer_user_id' => $order->buyer_user_id,
            'seller_user_id' => $order->seller_user_id,
            'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
            'gross_amount' => (string) $order->gross_amount,
            'currency' => $order->currency,
        ];

        DB::transaction(function () use ($order, $actor, $request, $before): void {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $orderId = (int) $locked->id;

            $this->deleteRelatedOrderRecords($orderId);
            $locked->delete();

            AuditLogWriter::write(
                actorUserId: $actor->id,
                action: 'admin.order.deleted',
                targetType: 'order',
                targetId: $orderId,
                beforeJson: $before,
                afterJson: ['deleted' => true],
                reasonCode: 'order_management',
                correlationId: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return redirect()->route('admin.orders.index')->with('success', 'Order deleted.');
    }

    private function deleteRelatedOrderRecords(int $orderId): void
    {
        $orderItemIds = $this->idsFor('order_items', 'order_id', $orderId);
        $disputeCaseIds = $this->idsFor('dispute_cases', 'order_id', $orderId);
        $escrowAccountIds = $this->idsFor('escrow_accounts', 'order_id', $orderId);
        $returnRequestIds = $this->idsFor('return_requests', 'order_id', $orderId);
        $chatThreadIds = $this->idsFor('chat_threads', 'order_id', $orderId);

        $this->deleteWhereIn('order_message_attachments', 'order_id', [$orderId]);
        $this->deleteWhereIn('digital_delivery_files', 'order_id', [$orderId]);
        $this->deleteWhereIn('digital_deliveries', 'order_id', [$orderId]);
        $this->deleteWhereIn('escrow_timeout_events', 'order_id', [$orderId]);

        $this->deleteWhereIn('chat_thread_reads', 'thread_id', $chatThreadIds);
        $this->deleteWhereIn('chat_messages', 'thread_id', $chatThreadIds);
        $this->deleteWhereIn('chat_threads', 'id', $chatThreadIds);

        $this->deleteWhereIn('return_request_events', 'return_request_id', $returnRequestIds);
        $this->deleteWhereIn('return_requests', 'id', $returnRequestIds);

        $this->deleteWhereIn('dispute_decisions', 'dispute_case_id', $disputeCaseIds);
        $this->deleteWhereIn('dispute_evidences', 'dispute_case_id', $disputeCaseIds);
        $this->deleteWhereIn('dispute_evidences', 'order_id', [$orderId]);
        $this->deleteWhereIn('dispute_cases', 'id', $disputeCaseIds);

        $this->deleteWhereIn('reviews', 'order_item_id', $orderItemIds);
        $this->deleteWhereIn('escrow_events', 'escrow_account_id', $escrowAccountIds);
        $this->deleteWhereIn('escrow_accounts', 'id', $escrowAccountIds);
        $this->deleteWhereIn('payment_transactions', 'order_id', [$orderId]);
        $this->deleteWhereIn('payment_intents', 'order_id', [$orderId]);
        $this->deleteWhereIn('order_state_transitions', 'order_id', [$orderId]);
        $this->deleteWhereIn('order_items', 'id', $orderItemIds);
    }

    /**
     * @return list<int>
     */
    private function idsFor(string $table, string $column, int $value): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)
            ->where($column, $value)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $values
     */
    private function deleteWhereIn(string $table, string $column, array $values): void
    {
        if ($values === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $values)->delete();
    }
}
