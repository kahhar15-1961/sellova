<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class UsersController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'header' => $this->pageHeader(
                'Users',
                'Accounts, risk flags, and lifecycle — list views will load from existing models.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Users'],
                ],
            ),
            'rows' => [],
            'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0],
        ]);
    }
}
