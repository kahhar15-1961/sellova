<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\AppServices;
use App\Http\Responses\ApiEnvelope;
use Symfony\Component\HttpFoundation\Response;

final class CategoryController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function index(): Response
    {
        $items = $this->app->categoryService()->listActiveRootCategories();

        return ApiEnvelope::data($items);
    }
}

