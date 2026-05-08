<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\AppServices;
use App\Http\Responses\ApiEnvelope;
use App\Models\Promotion;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PromotionAdminController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function index(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        if (! $actor->isPlatformStaff()) {
            return ApiEnvelope::error('forbidden', 'Platform staff access required.', Response::HTTP_FORBIDDEN, [
                'reason_code' => 'platform_staff_required',
                'action' => 'manage_promotions',
                'actor_user_id' => (int) $actor->id,
            ]);
        }

        return ApiEnvelope::data([
            'items' => $this->app->promotionService()->listAdminPromotions(),
        ]);
    }

    public function store(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        if (! $actor->isPlatformStaff()) {
            return ApiEnvelope::error('forbidden', 'Platform staff access required.', Response::HTTP_FORBIDDEN, [
                'reason_code' => 'platform_staff_required',
                'action' => 'manage_promotions',
                'actor_user_id' => (int) $actor->id,
            ]);
        }

        $payload = $this->payload($request);
        $created = $this->app->promotionService()->createPromotion($payload, (int) $actor->id);

        return ApiEnvelope::data($created, Response::HTTP_CREATED);
    }

    public function update(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        if (! $actor->isPlatformStaff()) {
            return ApiEnvelope::error('forbidden', 'Platform staff access required.', Response::HTTP_FORBIDDEN, [
                'reason_code' => 'platform_staff_required',
                'action' => 'manage_promotions',
                'actor_user_id' => (int) $actor->id,
            ]);
        }

        $promotion = $this->promotion($request);
        $updated = $this->app->promotionService()->updatePromotion($promotion, $this->payload($request));

        return ApiEnvelope::data($updated);
    }

    public function destroy(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        if (! $actor->isPlatformStaff()) {
            return ApiEnvelope::error('forbidden', 'Platform staff access required.', Response::HTTP_FORBIDDEN, [
                'reason_code' => 'platform_staff_required',
                'action' => 'manage_promotions',
                'actor_user_id' => (int) $actor->id,
            ]);
        }

        $promotion = $this->promotion($request);
        $deleted = $this->app->promotionService()->deletePromotion($promotion);

        return ApiEnvelope::data($deleted);
    }

    public function toggle(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        if (! $actor->isPlatformStaff()) {
            return ApiEnvelope::error('forbidden', 'Platform staff access required.', Response::HTTP_FORBIDDEN, [
                'reason_code' => 'platform_staff_required',
                'action' => 'manage_promotions',
                'actor_user_id' => (int) $actor->id,
            ]);
        }

        $promotion = $this->promotion($request);
        $body = $this->payload($request);
        $active = filter_var((string) ($body['is_active'] ?? true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $updated = $this->app->promotionService()->togglePromotion($promotion, $active ?? true);

        return ApiEnvelope::data($updated);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $raw = json_decode((string) $request->getContent(), true);
        return is_array($raw) ? $raw : [];
    }

    private function promotion(Request $request): Promotion
    {
        $id = (int) $request->attributes->get('promotionId');
        return Promotion::query()->whereKey($id)->firstOrFail();
    }
}
