<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UsersController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->lists->usersIndex($request);

        return Inertia::render('Admin/Users/Index', [
            'header' => $this->pageHeader(
                'Users',
                'Account directory with risk and role context.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Users'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.users.index'),
            'bulk_update_url' => route('admin.users.bulk-state'),
            'status_options' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'suspended', 'label' => 'Suspended'],
                ['value' => 'closed', 'label' => 'Closed'],
            ],
        ]);
    }
}
