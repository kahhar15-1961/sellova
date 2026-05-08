<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\AppServices;
use App\Http\Responses\ApiEnvelope;
use App\Services\PaymentGateway\PaymentGatewayService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PaymentGatewayController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function index(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $gateways = (new PaymentGatewayService())->enabled();

        return ApiEnvelope::data([
            'items' => array_map(
                static fn ($gateway): array => $gateway->publicPayload(),
                $gateways,
            ),
            'actor_user_id' => (int) $actor->id,
        ]);
    }
}
