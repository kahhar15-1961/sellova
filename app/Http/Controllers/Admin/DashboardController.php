<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends AdminPageController
{
    public function __construct(
        private readonly AdminDashboardService $dashboard,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $page = $this->dashboard->buildPage($user);

        return Inertia::render('Admin/Dashboard', [
            'header' => $this->pageHeader(
                'Dashboard',
                'Marketplace health, queues, and financial aggregates at a glance.',
                [
                    ['label' => 'Overview'],
                ],
            ),
            ...$page,
        ]);
    }
}
