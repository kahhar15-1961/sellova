<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'header' => $this->pageHeader(
                'Settings',
                'Platform configuration — connect to safe config store / feature flags when ready.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Settings'],
                ],
            ),
        ]);
    }
}
