<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreAdminEscalationPolicyRequest;
use App\Http\Requests\Admin\StoreAdminOnCallRotationRequest;
use App\Models\AdminEscalationPolicy;
use App\Models\AdminOnCallRotation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class AdminEscalationPoliciesController extends AdminPageController
{
    public function index(): Response
    {
        $policies = AdminEscalationPolicy::query()->orderBy('queue_code')->get();
        $rotations = AdminOnCallRotation::query()->with('user:id,email')->orderBy('role_code')->orderBy('priority')->get();

        return Inertia::render('Admin/EscalationPolicies/Index', [
            'header' => $this->pageHeader(
                'On-call Routing + Escalation Policies',
                'Policy matrix for severity, ack/resolve targets, and role-based on-call assignment.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Escalation Policies'],
                ],
            ),
            'policy_store_url' => route('admin.escalation-policies.store'),
            'rotation_store_url' => route('admin.escalation-policies.rotations.store'),
            'policies' => $policies->map(static fn (AdminEscalationPolicy $p): array => [
                'id' => $p->id,
                'queue_code' => $p->queue_code,
                'default_severity' => $p->default_severity,
                'auto_assign_on_call' => $p->auto_assign_on_call,
                'on_call_role_code' => $p->on_call_role_code,
                'ack_sla_minutes' => $p->ack_sla_minutes,
                'resolve_sla_minutes' => $p->resolve_sla_minutes,
                'comms_integration_id' => $p->comms_integration_id,
                'is_enabled' => $p->is_enabled,
            ])->values()->all(),
            'rotations' => $rotations->map(static fn (AdminOnCallRotation $r): array => [
                'id' => $r->id,
                'role_code' => $r->role_code,
                'user_id' => $r->user_id,
                'user_email' => $r->user?->email ?? '—',
                'weekday' => $r->weekday,
                'window' => sprintf('%02d:00-%02d:59', $r->start_hour, $r->end_hour),
                'priority' => $r->priority,
                'is_active' => $r->is_active,
            ])->values()->all(),
            'users' => User::query()->whereNull('deleted_at')->orderBy('email')->limit(400)->get(['id', 'email'])
                ->map(static fn (User $u): array => ['id' => $u->id, 'email' => $u->email ?? ('User #'.$u->id)])
                ->values()->all(),
        ]);
    }

    public function storePolicy(StoreAdminEscalationPolicyRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $queue = (string) $payload['queue_code'];

        AdminEscalationPolicy::query()->updateOrCreate(
            ['queue_code' => $queue],
            [
                'default_severity' => $payload['default_severity'],
                'auto_assign_on_call' => (bool) $payload['auto_assign_on_call'],
                'on_call_role_code' => $payload['on_call_role_code'] ?: null,
                'ack_sla_minutes' => (int) $payload['ack_sla_minutes'],
                'resolve_sla_minutes' => (int) $payload['resolve_sla_minutes'],
                'comms_integration_id' => $payload['comms_integration_id'] ?: null,
                'is_enabled' => (bool) $payload['is_enabled'],
            ],
        );

        return back()->with('success', 'Escalation policy saved.');
    }

    public function storeRotation(StoreAdminOnCallRotationRequest $request): RedirectResponse
    {
        AdminOnCallRotation::query()->create($request->validated());

        return back()->with('success', 'On-call rotation added.');
    }
}
