<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Commands\Product\CreateProductCommand;
use App\Domain\Value\ProductDraft;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Admin\AdminListsService;
use App\Services\Audit\AuditLogWriter;
use App\Services\Product\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class ProductsController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
        private readonly ProductService $products,
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
            'bulk_discount_url' => route('admin.products.bulk-discount'),
            'store_url' => route('admin.products.store'),
            'seller_options' => SellerProfile::query()
                ->with(['storefront:id,seller_profile_id,title'])
                ->orderBy('display_name')
                ->orderBy('id')
                ->limit(500)
                ->get(['id', 'display_name', 'legal_name', 'verification_status', 'store_status'])
                ->map(static fn (SellerProfile $seller): array => [
                    'value' => (string) $seller->id,
                    'label' => trim((string) ($seller->display_name ?? $seller->legal_name ?? 'Seller #'.$seller->id)),
                    'status' => (string) $seller->store_status,
                    'verification' => (string) $seller->verification_status,
                    'storefront' => $seller->storefront?->title,
                ])
                ->values()
                ->all(),
            'category_options' => Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (Category $category): array => [
                    'value' => (string) $category->id,
                    'label' => (string) $category->name,
                ])
                ->values()
                ->all(),
            'status_options' => collect(['draft', 'active', 'inactive', 'archived', 'published'])
                ->map(static fn (string $s): array => ['value' => $s, 'label' => ucfirst($s)])
                ->all(),
            'type_options' => [
                ['value' => 'physical', 'label' => 'Physical'],
                ['value' => 'digital', 'label' => 'Digital'],
                ['value' => 'instant_delivery', 'label' => 'Instant delivery'],
                ['value' => 'service', 'label' => 'Service'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'seller_profile_id' => ['required', 'integer', 'min:1', 'exists:seller_profiles,id'],
            'category_id' => ['required', 'integer', 'min:1', 'exists:categories,id'],
            'product_type' => ['required', 'string', 'in:physical,digital,instant_delivery,service'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:95'],
            'discount_label' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:draft,active,inactive,archived,published'],
            'launch_at' => ['nullable', 'date'],
            'primary_image' => ['nullable', 'image', 'max:5120'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'max:5120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'condition' => ['nullable', 'string', 'max:80'],
            'warranty_status' => ['nullable', 'string', 'max:120'],
            'product_location' => ['nullable', 'string', 'max:160'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $seller = SellerProfile::query()->with('storefront:id,seller_profile_id')->findOrFail((int) $data['seller_profile_id']);
        $images = [];
        $primary = $request->file('primary_image');
        if ($primary instanceof UploadedFile) {
            $images[] = $this->storeProductImage($seller, $primary);
        }
        foreach ($request->file('gallery_images', []) as $file) {
            if ($file instanceof UploadedFile) {
                $images[] = $this->storeProductImage($seller, $file);
            }
        }

        $images = array_values(array_unique(array_filter($images)));
        $attributes = array_filter([
            'brand' => $data['brand'] ?? null,
            'condition' => $data['condition'] ?? null,
            'warranty_status' => $data['warranty_status'] ?? null,
            'product_location' => $data['product_location'] ?? null,
            'tags' => $this->tags($data['tags'] ?? ''),
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        $result = $this->products->createProduct(new CreateProductCommand(
            sellerProfileId: (int) $seller->id,
            draft: new ProductDraft(
                storefrontId: (int) ($seller->storefront?->id ?? 0),
                categoryId: (int) $data['category_id'],
                productType: (string) $data['product_type'],
                title: (string) $data['title'],
                description: isset($data['description']) ? (string) $data['description'] : null,
                basePrice: (string) $data['base_price'],
                currency: strtoupper((string) $data['currency']),
                stock: isset($data['stock']) ? (int) $data['stock'] : null,
                status: (string) $data['status'],
                discountPercentage: (string) ($data['discount_percentage'] ?? '0'),
                discountLabel: $data['discount_label'] ?? null,
                imageUrl: $images[0] ?? null,
                imageUrls: $images,
                attributes: $attributes,
            ),
        ));

        $product = Product::query()->find((int) ($result['id'] ?? 0));
        if ($product instanceof Product) {
            $status = (string) $data['status'];
            $product->forceFill([
                'status' => $status,
                'published_at' => $status === 'published' || $status === 'active'
                    ? ($data['launch_at'] ?? $product->published_at ?? now())
                    : null,
            ])->save();
        }

        return redirect()
            ->route('admin.products.show', $product ?? ($result['id'] ?? 0))
            ->with('success', 'Product created for seller.');
    }

    public function bulkDiscount(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'ids' => ['required_without:select_all', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
            'select_all' => ['nullable', 'boolean'],
            'filters' => ['nullable', 'array'],
            'filters.q' => ['nullable', 'string', 'max:255'],
            'filters.status' => ['nullable', 'string', 'in:draft,active,inactive,archived,published'],
            'filters.type' => ['nullable', 'string', 'in:physical,digital,instant_delivery,service'],
            'discount_percentage' => ['required', 'numeric', 'min:0', 'max:95'],
            'discount_label' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Product::query();
        if ((bool) ($data['select_all'] ?? false)) {
            $fq = trim((string) data_get($data, 'filters.q', ''));
            $fStatus = trim((string) data_get($data, 'filters.status', ''));
            $fType = trim((string) data_get($data, 'filters.type', ''));
            if ($fStatus !== '') {
                $query->where('status', $fStatus);
            }
            if ($fType !== '') {
                $query->where('product_type', $fType);
            }
            if ($fq !== '') {
                $query->where(static function ($w) use ($fq): void {
                    $w->where('title', 'like', '%'.$fq.'%')
                        ->orWhere('id', $fq);
                });
            }
        } else {
            $query->whereIn('id', array_values(array_unique(array_map('intval', $data['ids'] ?? []))));
        }

        $discountPercentage = number_format((float) $data['discount_percentage'], 2, '.', '');
        $discountLabel = trim((string) ($data['discount_label'] ?? '')) ?: null;
        $updated = 0;

        foreach ($query->get() as $product) {
            $before = [
                'discount_percentage' => $product->discount_percentage,
                'discount_label' => $product->discount_label,
            ];

            $product->forceFill([
                'discount_percentage' => $discountPercentage,
                'discount_label' => $discountLabel,
            ])->save();
            $updated++;

            AuditLogWriter::write(
                actorUserId: $actor->id,
                action: 'admin.product.bulk_discount_updated',
                targetType: 'product',
                targetId: $product->id,
                beforeJson: $before,
                afterJson: [
                    'discount_percentage' => $product->discount_percentage,
                    'discount_label' => $product->discount_label,
                ],
                reasonCode: $discountLabel !== null ? 'campaign_discount:'.$discountLabel : 'campaign_discount',
                correlationId: null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return redirect()->route('admin.products.index')->with('success', "Discount updated for {$updated} product(s).");
    }

    /**
     * @return list<string>
     */
    private function tags(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function storeProductImage(SellerProfile $seller, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $extension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'jpg';
        $safeName = (string) Str::uuid().'.'.$extension;
        $relativeDir = sprintf('seller-uploads/%d/product_image', (int) $seller->user_id);

        Storage::disk('local')->putFileAs($relativeDir, $file, $safeName);

        return $relativeDir.'/'.$safeName;
    }
}
