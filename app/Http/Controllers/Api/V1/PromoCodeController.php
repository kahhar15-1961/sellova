<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\AppServices;
use App\Http\Requests\V1\ValidatePromoCodeRequest;
use App\Http\Responses\ApiEnvelope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PromoCodeController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function index(Request $request): Response
    {
        $subtotalRaw = $request->query->get('subtotal');
        $subtotal = $subtotalRaw === null || $subtotalRaw === ''
            ? null
            : max(0.0, (float) $subtotalRaw);
        $currency = strtoupper(trim((string) ($request->query->get('currency') ?? 'USD')));
        $shippingMethod = strtolower(trim((string) ($request->query->get('shipping_method') ?? 'standard')));

        return ApiEnvelope::data([
            'items' => $this->app->promotionService()->listPromotions($subtotal, $currency !== '' ? $currency : 'USD', $shippingMethod),
        ]);
    }

    public function validate(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $payload = ValidatePromoCodeRequest::payload($request);
        $result = $this->app->promotionService()->validatePromoCode(
            rawCode: (string) $payload['code'],
            subtotal: (float) $payload['subtotal'],
            shippingFee: (float) $payload['shipping_fee'],
            currency: (string) $payload['currency'],
            shippingMethod: (string) $payload['shipping_method'],
        );

        return ApiEnvelope::data([
            'actor_user_id' => (int) $actor->id,
            'promo' => $result,
        ]);
    }
}
