<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Auth\Ability;
use App\Domain\Commands\Dispute\OpenDisputeCommand;
use App\Domain\Commands\Order\CompleteOrderCommand;
use App\Http\AppServices;
use App\Http\Responses\ApiEnvelope;
use App\Http\Support\AggregateHttpLookup;
use App\Models\DigitalDeliveryFile;
use App\Models\OrderMessageAttachment;
use App\Services\DigitalDelivery\DigitalDeliveryService;
use App\Services\Dispute\DisputeService;
use App\Services\Order\EscrowOrderDetailService;
use App\Services\Order\OrderMessageService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class EscrowOrderController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function show(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $order = AggregateHttpLookup::order((int) $request->attributes->get('orderId'));
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        return ApiEnvelope::data(
            app(EscrowOrderDetailService::class)->build($order, (int) $actor->id, $actor->isPlatformStaff())
        );
    }

    public function countdown(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $order = AggregateHttpLookup::order((int) $request->attributes->get('orderId'));
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);
        $detail = app(EscrowOrderDetailService::class)->build($order, (int) $actor->id, $actor->isPlatformStaff());

        return ApiEnvelope::data($detail['escrow']['timer'] ?? []);
    }

    public function release(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $order = AggregateHttpLookup::order((int) $request->attributes->get('orderId'));
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        app(DigitalDeliveryService::class)->confirmAccepted($order, (int) $actor->id, 'api:release:'.$order->id);
        $this->app->orderService()->completeOrder(new CompleteOrderCommand(
            orderId: (int) $order->id,
            actorUserId: (int) $actor->id,
            correlationId: 'api:release:'.$order->id,
        ));

        return ApiEnvelope::data(
            app(EscrowOrderDetailService::class)->build($order->fresh(), (int) $actor->id, $actor->isPlatformStaff())
        );
    }

    public function openDispute(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $order = AggregateHttpLookup::order((int) $request->attributes->get('orderId'));
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);
        $body = json_decode($request->getContent(), true);
        $reasonCode = trim((string) (($body['reason_code'] ?? '') ?: 'delivery_issue'));

        app(DisputeService::class)->openDispute(new OpenDisputeCommand(
            orderId: (int) $order->id,
            orderItemId: null,
            openedByUserId: (int) $actor->id,
            reasonCode: $reasonCode,
            correlationId: 'api:dispute:'.$order->id,
            idempotencyKey: 'api:dispute:'.$order->id,
        ));

        return ApiEnvelope::data(
            app(EscrowOrderDetailService::class)->build($order->fresh(), (int) $actor->id, $actor->isPlatformStaff())
        );
    }

    public function submitDelivery(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $order = AggregateHttpLookup::order((int) $request->attributes->get('orderId'));
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $payload = $request->request->all();
        $delivery = app(DigitalDeliveryService::class)->submitDelivery(
            order: $order,
            actorUserId: (int) $actor->id,
            note: isset($payload['delivery_message']) ? (string) $payload['delivery_message'] : null,
            externalUrl: isset($payload['external_delivery_url']) ? (string) $payload['external_delivery_url'] : null,
            version: isset($payload['delivery_version']) ? (string) $payload['delivery_version'] : null,
            files: array_values((array) $request->files->get('files', [])),
            correlationId: 'api:delivery:'.$order->id,
        );

        return ApiEnvelope::data($delivery, Response::HTTP_CREATED);
    }

    public function messages(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $order = AggregateHttpLookup::order((int) $request->attributes->get('orderId'));
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        return ApiEnvelope::data(
            app(OrderMessageService::class)->listMessages($order, (int) $actor->id, $actor->isPlatformStaff())
        );
    }

    public function sendMessage(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $order = AggregateHttpLookup::order((int) $request->attributes->get('orderId'));
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        $payload = $request->request->all();
        $message = app(OrderMessageService::class)->sendMessage(
            order: $order,
            senderUserId: (int) $actor->id,
            body: (string) ($payload['body'] ?? ''),
            attachments: array_values((array) $request->files->get('attachments', [])),
            artifactType: isset($payload['artifact_type']) ? (string) $payload['artifact_type'] : null,
            allowAdmin: $actor->isPlatformStaff(),
        );

        return ApiEnvelope::data($message, Response::HTTP_CREATED);
    }

    public function markRead(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $order = AggregateHttpLookup::order((int) $request->attributes->get('orderId'));
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);
        app(OrderMessageService::class)->markRead($order, (int) $actor->id, $actor->isPlatformStaff());

        return ApiEnvelope::data(['ok' => true]);
    }

    public function timeline(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $order = AggregateHttpLookup::order((int) $request->attributes->get('orderId'));
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);
        $detail = app(EscrowOrderDetailService::class)->build($order, (int) $actor->id, $actor->isPlatformStaff());

        return ApiEnvelope::data([
            'timeline' => $detail['timeline'] ?? [],
            'activity_timeline' => $detail['activity_timeline'] ?? [],
        ]);
    }

    public function downloadDeliveryFile(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $file = DigitalDeliveryFile::query()->findOrFail((int) $request->attributes->get('digitalDeliveryFileId'));
        $order = AggregateHttpLookup::order((int) $file->order_id);
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        return Storage::disk((string) $file->disk)->download((string) $file->path, (string) $file->original_name);
    }

    public function downloadMessageAttachment(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $file = OrderMessageAttachment::query()->findOrFail((int) $request->attributes->get('orderMessageAttachmentId'));
        $order = AggregateHttpLookup::order((int) $file->order_id);
        $this->app->domainGate()->authorize(Ability::OrderView, $actor, $order);

        return Storage::disk((string) $file->disk)->download((string) $file->path, (string) $file->original_name);
    }
}
