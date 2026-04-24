<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
        $this->app->requireActor($request);
        StoreProductRequest::payload($request);

        return ApiEnvelope::notImplemented('products', 'createProduct');
    }

    public function update(Request $request): Response
    {
        $this->app->requireActor($request);
        UpdateProductRequest::payload($request);

        return ApiEnvelope::notImplemented('products', 'updateProduct');
    }

    public function destroy(Request $request): Response
    {
        $this->app->requireActor($request);
        CorrelationIdOptionalRequest::payload($request);

        return ApiEnvelope::notImplemented('products', 'deleteProduct');
    }
}
