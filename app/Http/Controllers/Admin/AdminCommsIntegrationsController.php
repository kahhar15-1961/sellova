<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreAdminCommsIntegrationRequest;
use App\Models\AdminCommsIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;
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
        $payload = $request->validated();
        AdminCommsIntegration::query()->updateOrCreate(
            ['name' => (string) $payload['name']],
            $payload,
        );

        return back()->with('success', 'Comms integration saved.');
    }

    public function test(Request $request): RedirectResponse
    {
        $integrationId = (int) $request->input('integration_id', 0);
        $integration = AdminCommsIntegration::query()->when(
            $integrationId > 0,
            static fn ($q) => $q->whereKey($integrationId),
            static fn ($q) => $q->where('is_enabled', true)->orderByDesc('id'),
        )->first();

        if ($integration === null) {
            return back()->with('error', 'No integration found to test.');
        }

        try {
            if ($integration->channel === 'webhook' && $integration->webhook_url) {
                Http::timeout(5)->post((string) $integration->webhook_url, [
                    'event' => 'admin.comms.test',
                    'time' => now()->toIso8601String(),
                    'source' => 'sellova-admin',
                    'integration' => $integration->name,
                ])->throw();
            } elseif ($integration->channel === 'email' && $integration->email_to) {
                Mail::raw('Sellova admin comms integration test message.', static function ($msg) use ($integration): void {
                    $msg->to((string) $integration->email_to)
                        ->subject('Sellova Admin Comms Test');
                });
            } else {
                return back()->with('error', 'Integration is missing required endpoint configuration.');
            }
        } catch (Throwable $e) {
            return back()->with('error', 'Integration test failed: '.$e->getMessage());
        }

        $integration->forceFill(['last_tested_at' => now()])->save();

        return back()->with('success', 'Integration test sent.');
    }
}
