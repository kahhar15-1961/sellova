<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\AppServices;
use App\Http\Responses\ApiEnvelope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CategoryController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function index(): Response
    {
        $items = $this->app->categoryService()->listActiveCategories();

        return ApiEnvelope::data($items);
    }

    public function request(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = $request->request->all();
        }
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return ApiEnvelope::error('validation_failed', 'Category name is required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $row = $this->app->categoryService()->submitSellerRequest(
                $actor,
                $name,
                isset($payload['parent_id']) && is_numeric($payload['parent_id']) ? (int) $payload['parent_id'] : null,
                isset($payload['reason']) ? (string) $payload['reason'] : null,
                isset($payload['example_product_name']) ? (string) $payload['example_product_name'] : null,
            );
        } catch (\RuntimeException) {
            return ApiEnvelope::error('not_found', 'Seller profile not found.', Response::HTTP_NOT_FOUND);
        }

        return ApiEnvelope::created([
            'id' => $row->id,
            'name' => $row->name,
            'status' => $row->status,
        ]);
    }
}
