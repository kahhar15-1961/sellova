<?php

namespace App\Services\DigitalDelivery;

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\ProductType;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Models\DigitalDelivery;
use App\Models\DigitalDeliveryFile;
use App\Models\Order;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Services\Order\OrderMessageService;
use App\Services\Order\OrderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DigitalDeliveryService
{
    public function __construct(
        private readonly OrderService $orders = new OrderService(),
        private readonly OrderMessageService $messages = new OrderMessageService(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly AuditService $audit = new AuditService(),
    ) {
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<string, mixed>
     */
    public function submitDelivery(
        Order $order,
        int $actorUserId,
        ?string $note,
        ?string $externalUrl,
        ?string $version,
        array $files = [],
        ?string $correlationId = null,
    ): array {
        return DB::transaction(function () use ($order, $actorUserId, $note, $externalUrl, $version, $files, $correlationId): array {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ((int) ($locked->seller_user_id ?? 0) !== $actorUserId) {
                throw new OrderValidationFailedException($locked->id, 'only_seller_can_submit_delivery');
            }

            $type = ProductType::normalize((string) $locked->product_type);
            if (! $type->requiresDeliveryChat()) {
                throw new OrderValidationFailedException($locked->id, 'digital_delivery_only');
            }

            $note = trim((string) $note);
            $externalUrl = trim((string) $externalUrl);
            $version = trim((string) $version);
            if ($note === '' && $externalUrl === '' && $files === []) {
                throw new OrderValidationFailedException($locked->id, 'delivery_payload_required');
            }

            $current = $locked->latestDigitalDelivery()->first();
            $status = $current?->status === 'revision_requested' ? 'delivered' : 'delivered';
            $delivery = $current instanceof DigitalDelivery && in_array($current->status, ['pending', 'preparing', 'revision_requested'], true)
                ? $current
                : new DigitalDelivery([
                    'uuid' => (string) Str::uuid(),
                    'order_id' => (int) $locked->id,
                    'seller_user_id' => $actorUserId,
                    'buyer_user_id' => (int) $locked->buyer_user_id,
                ]);

            $delivery->status = $status;
            $delivery->version = $version !== '' ? $version : ($delivery->version ?: 'v1');
            $delivery->external_url = $externalUrl !== '' ? $externalUrl : null;
            $delivery->delivery_note = $note !== '' ? $note : null;
            $delivery->delivered_at = now();
            $delivery->save();

            foreach ($files as $file) {
                $this->storeDeliveryFile($locked, $delivery, $actorUserId, $file);
            }

            $delivery->files_count = $delivery->files()->count();
            $delivery->save();

            $this->orders->submitDelivery(
                orderId: (int) $locked->id,
                actorUserId: $actorUserId,
                note: $note !== '' ? $note : ($externalUrl !== '' ? 'Delivery link shared.' : null),
                correlationId: $correlationId,
            );

            $locked->refresh();
            $locked->delivery_status = 'delivered';
            $locked->delivery_note = $delivery->delivery_note;
            $locked->delivery_version = $delivery->version;
            $locked->delivery_files_count = (int) $delivery->files_count;
            $locked->delivered_at = $delivery->delivered_at;
            $locked->save();

            $messageBody = $note !== '' ? $note : 'Delivery submitted.';
            $this->messages->sendMessage(
                order: $locked,
                senderUserId: $actorUserId,
                body: $messageBody,
                attachments: [],
                artifactType: 'delivery_submission',
                isDeliveryProof: true,
            );

            $this->notifications->notify(
                (int) $locked->buyer_user_id,
                'escrow.delivery.submitted',
                'Delivery submitted',
                'Your seller submitted a digital delivery for order '.($locked->order_number ?? '#'.$locked->id).'.',
                [
                    'order_id' => (int) $locked->id,
                    'delivery_id' => (int) $delivery->id,
                ],
            );

            $this->audit->record(
                actorId: $actorUserId,
                actorRole: 'seller',
                action: 'escrow.delivery.submitted',
                targetType: 'order',
                targetId: (int) $locked->id,
                before: ['delivery_status' => $order->delivery_status],
                after: ['delivery_status' => $locked->delivery_status, 'delivery_id' => (int) $delivery->id],
                reasonCode: 'seller_submitted_delivery',
                correlationId: $correlationId,
            );

            return $delivery->fresh(['files'])->toArray();
        });
    }

    public function requestRevision(Order $order, int $actorUserId, ?string $note = null, ?string $correlationId = null): array
    {
        return DB::transaction(function () use ($order, $actorUserId, $note, $correlationId): array {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->buyer_user_id !== $actorUserId) {
                throw new OrderValidationFailedException($locked->id, 'only_buyer_can_request_revision');
            }

            $delivery = $locked->latestDigitalDelivery()->first();
            if (! $delivery instanceof DigitalDelivery) {
                throw new OrderValidationFailedException($locked->id, 'delivery_not_found');
            }

            $delivery->status = 'revision_requested';
            $delivery->revision_requested_at = now();
            $delivery->save();

            $locked->delivery_status = 'revision_requested';
            $locked->save();

            $this->messages->sendMessage(
                order: $locked,
                senderUserId: $actorUserId,
                body: trim((string) $note) !== '' ? trim((string) $note) : 'Please revise the delivery.',
                attachments: [],
                artifactType: 'revision_request',
            );

            $this->notifications->notify(
                (int) ($locked->seller_user_id ?? 0),
                'escrow.delivery.revision_requested',
                'Revision requested',
                'The buyer asked for a revision on order '.($locked->order_number ?? '#'.$locked->id).'.',
                ['order_id' => (int) $locked->id],
            );

            $this->audit->record(
                actorId: $actorUserId,
                actorRole: 'buyer',
                action: 'escrow.delivery.revision_requested',
                targetType: 'order',
                targetId: (int) $locked->id,
                before: ['delivery_status' => 'delivered'],
                after: ['delivery_status' => 'revision_requested'],
                reasonCode: 'buyer_requested_revision',
                correlationId: $correlationId,
            );

            return $delivery->fresh(['files'])->toArray();
        });
    }

    public function confirmAccepted(Order $order, int $actorUserId, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($order, $actorUserId, $correlationId): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->buyer_user_id !== $actorUserId) {
                throw new OrderValidationFailedException($locked->id, 'only_buyer_can_accept_delivery');
            }

            $delivery = $locked->latestDigitalDelivery()->first();
            if ($delivery instanceof DigitalDelivery) {
                $delivery->status = 'accepted';
                $delivery->buyer_confirmed_at = now();
                $delivery->accepted_at = now();
                $delivery->save();
            }

            $locked->buyer_confirmed_at = now();
            $locked->delivery_status = 'accepted';
            $locked->save();

            $this->audit->record(
                actorId: $actorUserId,
                actorRole: 'buyer',
                action: 'escrow.delivery.accepted',
                targetType: 'order',
                targetId: (int) $locked->id,
                before: ['delivery_status' => $order->delivery_status],
                after: ['delivery_status' => 'accepted'],
                reasonCode: 'buyer_accepted_delivery',
                correlationId: $correlationId,
            );
        });
    }

    private function storeDeliveryFile(Order $order, DigitalDelivery $delivery, int $actorUserId, UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new OrderValidationFailedException($order->id, 'delivery_file_upload_failed');
        }

        $size = (int) ($file->getSize() ?? 0);
        if ($size <= 0 || $size > 25 * 1024 * 1024) {
            throw new OrderValidationFailedException($order->id, 'delivery_file_size_invalid');
        }

        $storedName = Str::uuid()->toString().'.'.strtolower((string) $file->getClientOriginalExtension());
        $path = $file->storeAs('private/escrow-deliveries/'.$order->id, $storedName, 'local');

        DigitalDeliveryFile::query()->create([
            'uuid' => (string) Str::uuid(),
            'digital_delivery_id' => (int) $delivery->id,
            'order_id' => (int) $order->id,
            'uploaded_by_user_id' => $actorUserId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => (string) ($file->getClientOriginalName() ?: $storedName),
            'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
            'size_bytes' => $size,
            'visibility' => 'escrow',
            'scan_status' => 'pending',
        ]);
    }
}
