<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends AdminPageController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'header' => $this->pageHeader(
                'Settings',
                'Read-only environment snapshot (no secrets). Operational toggles remain in .env and your deploy pipeline.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Settings'],
                ],
            ),
            'environment' => [
                'app_name' => (string) Config::get('app.name'),
                'app_env' => (string) Config::get('app.env'),
                'app_debug' => (bool) Config::get('app.debug'),
                'app_url' => (string) Config::get('app.url'),
                'db_connection' => (string) Config::get('database.default'),
                'cache_store' => (string) Config::get('cache.default'),
                'session_driver' => (string) Config::get('session.driver'),
                'queue_connection' => (string) Config::get('queue.default'),
                'mail_mailer' => (string) Config::get('mail.default'),
            ],
        ]);
    }
}
