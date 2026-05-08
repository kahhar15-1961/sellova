<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Support\AggregateHttpLookup;
use App\Domain\Queries\Catalog\ProductCatalogListQuery;
use App\Http\AppServices;
use App\Http\Requests\V1\CorrelationIdOptionalRequest;
use App\Http\Requests\V1\StoreProductRequest;
use App\Http\Requests\V1\UpdateProductRequest;
use App\Http\Responses\ApiEnvelope;
use App\Http\Support\RequestPagination;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProductController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function index(Request $request): Response
    {
        $p = RequestPagination::pageAndPerPage($request);
        $query = new ProductCatalogListQuery(
            page: $p['page'],
            perPage: $p['per_page'],
            search: null,
            categoryId: RequestPagination::optionalPositiveInt($request->query->get('category_id')),
            storefrontId: RequestPagination::optionalPositiveInt($request->query->get('storefront_id')),
        );
        $result = $this->app->productService()->listPublishedProducts($query);

        return ApiEnvelope::paginated($result['items'], $result['page'], $result['per_page'], $result['total']);
    }

    public function search(Request $request): Response
    {
        $p = RequestPagination::pageAndPerPage($request);
        $search = trim((string) ($request->query->get('search') ?? ''));
        $query = new ProductCatalogListQuery(
            page: $p['page'],
            perPage: $p['per_page'],
            search: $search !== '' ? $search : null,
            categoryId: RequestPagination::optionalPositiveInt($request->query->get('category_id')),
            storefrontId: RequestPagination::optionalPositiveInt($request->query->get('storefront_id')),
        );
        $result = $this->app->productService()->searchPublishedProducts($query);

        return ApiEnvelope::paginated($result['items'], $result['page'], $result['per_page'], $result['total']);
    }

    public function show(Request $request): Response
    {
        $productId = (int) $request->attributes->get('productId');
        $data = $this->app->productService()->getPublishedProduct($productId);

        return ApiEnvelope::data($data);
    }

    public function store(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $command = StoreProductRequest::toCommand($request, $actor);
        $result = $this->app->productService()->createProduct($command);

        return ApiEnvelope::data($result, 201);
    }

    public function update(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $productId = (int) $request->attributes->get('productId');
        $product = AggregateHttpLookup::product($productId);
        $command = UpdateProductRequest::toCommand($request, $actor, $product);
        $result = $this->app->productService()->updateProduct($command);

        return ApiEnvelope::data($result);
    }

    public function sellerIndex(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $sellerProfile = $actor->sellerProfile;
        if ($sellerProfile === null) {
            return ApiEnvelope::error('not_found', 'Seller profile not found.', Response::HTTP_NOT_FOUND, [
                'reason_code' => 'seller_profile_not_found',
            ]);
        }

        $page = RequestPagination::pageAndPerPage($request);
        $result = $this->app->productService()->listSellerProducts(
            (int) $sellerProfile->id,
            $page['page'],
            $page['per_page'],
        );

        return ApiEnvelope::paginated($result['items'], $result['page'], $result['per_page'], $result['total']);
    }

    public function sellerStore(Request $request): Response
    {
        return $this->store($request);
    }

    public function sellerUpdate(Request $request): Response
    {
        return $this->update($request);
    }

    public function sellerToggle(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $sellerProfile = $actor->sellerProfile;
        if ($sellerProfile === null) {
            return ApiEnvelope::error('not_found', 'Seller profile not found.', Response::HTTP_NOT_FOUND, [
                'reason_code' => 'seller_profile_not_found',
            ]);
        }
        $productId = (int) $request->attributes->get('productId');
        $product = AggregateHttpLookup::product($productId);
        $payload = json_decode($request->getContent(), true);
        $active = filter_var((string) ($payload['active'] ?? true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $result = $this->app->productService()->toggleProductStatus($product, (int) $sellerProfile->id, $active ?? true);

        return ApiEnvelope::data($result);
    }

    public function destroy(Request $request): Response
    {
        $actor = $this->app->requireActor($request);
        $sellerProfile = $actor->sellerProfile;
        if ($sellerProfile === null) {
            return ApiEnvelope::error('not_found', 'Seller profile not found.', Response::HTTP_NOT_FOUND, [
                'reason_code' => 'seller_profile_not_found',
            ]);
        }

        $productId = (int) $request->attributes->get('productId');
        $product = AggregateHttpLookup::product($productId);
        CorrelationIdOptionalRequest::payload($request);
        $result = $this->app->productService()->deleteProduct($product, (int) $sellerProfile->id);

        return ApiEnvelope::data($result);
    }
}
