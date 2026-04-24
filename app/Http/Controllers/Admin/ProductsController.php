<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class ProductsController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Products/Index', [
            'header' => $this->pageHeader(
                'Products & moderation',
                'Catalog visibility, compliance flags, and bulk actions — to integrate ProductService.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Marketplace'],
                    ['label' => 'Products'],
                ],
            ),
            'rows' => [],
            'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0],
        ]);
    }
}
