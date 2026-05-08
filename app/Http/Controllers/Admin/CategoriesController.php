<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Http\AppServices;
use App\Models\Category;
use App\Models\SellerCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CategoriesController extends AdminPageController
{
    public function __construct(private readonly AppServices $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('Admin/Categories/Index', [
            'header' => $this->pageHeader(
                'Categories',
                'Manage the marketplace taxonomy and review seller category requests.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Marketplace'],
                    ['label' => 'Categories'],
                ],
            ),
            'categories' => $this->app->categoryService()->listAdminCategories(),
            'requests' => $this->app->categoryService()->listAdminRequests(),
            'store_url' => route('admin.categories.store'),
            'update_url_template' => route('admin.categories.update', ['category' => '__ID__']),
            'toggle_url_template' => route('admin.categories.toggle', ['category' => '__ID__']),
            'approve_request_url_template' => route('admin.category-requests.approve', ['categoryRequest' => '__ID__']),
            'reject_request_url_template' => route('admin.category-requests.reject', ['categoryRequest' => '__ID__']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->app->categoryService()->createCategory($this->validateCategory($request));

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->app->categoryService()->updateCategory($category, $this->validateCategory($request, partial: true));

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function toggle(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeManage($request);
        $category->forceFill([
            'is_active' => filter_var((string) $request->input('is_active', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        ])->save();

        return redirect()->route('admin.categories.index')->with('success', 'Category status updated.');
    }

    public function approveRequest(Request $request, SellerCategoryRequest $categoryRequest): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'admin_note' => ['nullable', 'string'],
        ]);
        $this->app->categoryService()->approveRequest(
            $categoryRequest,
            $request->user(),
            isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            $data['admin_note'] ?? null,
        );

        return redirect()->route('admin.categories.index')->with('success', 'Category request approved.');
    }

    public function rejectRequest(Request $request, SellerCategoryRequest $categoryRequest): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'admin_note' => ['nullable', 'string'],
        ]);
        $this->app->categoryService()->rejectRequest($categoryRequest, $request->user(), $data['admin_note'] ?? null);

        return redirect()->route('admin.categories.index')->with('success', 'Category request rejected.');
    }

    private function authorizeManage(Request $request): void
    {
        $actor = $request->user();
        if ($actor === null || (! $actor->isPlatformStaff() && ! $actor->hasPermissionCode(AdminPermission::PRODUCTS_MODERATE))) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'name' => [$required, 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:512'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable'],
        ]);
    }
}
