<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Application;
use App\Http\Requests\V1\CorrelationIdOptionalRequest;
use App\Http\Requests\V1\StoreProductRequest;
use App\Http\Requests\V1\UpdateProductRequest;
use App\Http\Responses\ApiEnvelope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProductController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->app->requireActor($request);

        return ApiEnvelope::notImplemented('products', 'listProducts');
    }

    public function show(Request $request): Response
    {
        $this->app->requireActor($request);

        return ApiEnvelope::notImplemented('products', 'showProduct');
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
