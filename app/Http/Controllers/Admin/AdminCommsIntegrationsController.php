<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreAdminCommsIntegrationRequest;
use App\Models\AdminCommsIntegration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

final class AdminCommsIntegrationsController extends AdminPageController
{
    public function index(): Response
    {
        $integrations = AdminCommsIntegration::query()->orderBy('name')->get();

        return Inertia::render('Admin/CommsIntegrations/Index', [
            'header' => $this->pageHeader(
                'Comms Integrations',
                'Outbound incident notifications to webhook/email endpoints for ops and incident channels.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Comms Integrations'],
                ],
            ),
            'store_url' => route('admin.comms-integrations.store'),
            'test_url' => route('admin.comms-integrations.test'),
            'rows' => $integrations->map(static fn (AdminCommsIntegration $i): array => [
                'id' => $i->id,
                'name' => $i->name,
                'channel' => $i->channel,
                'is_enabled' => $i->is_enabled,
                'webhook_url' => $i->webhook_url,
                'email_to' => $i->email_to,
                'last_tested_at' => $i->last_tested_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function store(StoreAdminCommsIntegrationRequest $request): RedirectResponse
    {
        AdminCommsIntegration::query()->create($request->validated());

        return back()->with('success', 'Comms integration added.');
    }

    public function test(): RedirectResponse
    {
        $integration = AdminCommsIntegration::query()
            ->where('channel', 'webhook')
            ->whereNotNull('webhook_url')
            ->where('is_enabled', true)
            ->orderByDesc('id')
            ->first();

        if ($integration === null) {
            return back()->with('error', 'No enabled webhook integration to test.');
        }

        Http::timeout(5)->post((string) $integration->webhook_url, [
            'event' => 'admin.comms.test',
            'time' => now()->toIso8601String(),
            'source' => 'sellova-admin',
        ]);
        $integration->forceFill(['last_tested_at' => now()])->save();

        return back()->with('success', 'Webhook test sent.');
    }
}
