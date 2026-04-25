<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProductsController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->lists->productsIndex($request);

        return Inertia::render('Admin/Products/Index', [
            'header' => $this->pageHeader(
                'Products & moderation',
                'Catalog listings with moderation status and seller attribution.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Marketplace'],
                    ['label' => 'Products'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.products.index'),
            'bulk_moderate_url' => route('admin.products.bulk-moderate'),
            'status_options' => collect(['draft', 'active', 'inactive', 'archived', 'published'])
                ->map(static fn (string $s): array => ['value' => $s, 'label' => ucfirst($s)])
                ->all(),
        ]);
    }
}
