<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AdminEscalationIncident;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AdminEscalationsInboxController extends AdminPageController
{
    public function __invoke(Request $request): Response
    {
        $status = (string) $request->query('status', 'open');
        $queue = (string) $request->query('queue', '');
        $q = trim((string) $request->query('q', ''));

        $builder = AdminEscalationIncident::query()
            ->with(['assigned_user:id,email'])
            ->orderByRaw("FIELD(status, 'open', 'acknowledged', 'resolved')")
            ->orderByDesc('opened_at');

        if ($status !== 'all') {
            $builder->where('status', $status);
        }
        if ($queue !== '') {
            $builder->where('queue_code', $queue);
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('target_type', 'like', '%'.$q.'%')
                    ->orWhere('reason_code', 'like', '%'.$q.'%')
                    ->orWhere('target_id', $q);
            });
        }

        $incidents = $builder->limit(200)->get();

        return Inertia::render('Admin/Escalations/Index', [
            'header' => $this->pageHeader(
                'Escalations Inbox',
                'Central incident queue for SLA breaches with on-call assignment and resolution workflow.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Escalations'],
                ],
            ),
            'filters' => ['status' => $status, 'queue' => $queue, 'q' => $q],
            'index_url' => route('admin.escalations.index'),
            'action_url' => route('admin.escalations.action'),
            'slo_export_url' => route('admin.escalations.slo-export', ['days' => 30]),
            'rows' => $incidents->map(static fn (AdminEscalationIncident $i): array => [
                'id' => $i->id,
                'queue' => $i->queue_code,
                'target' => $i->target_type.' #'.$i->target_id,
                'href' => route('admin.escalations.show', $i),
                'severity' => $i->severity,
                'status' => $i->status,
                'reason' => $i->reason_code ?? '—',
                'assignee' => $i->assigned_user?->email ?? 'Unassigned',
                'assignee_user_id' => $i->assigned_user_id,
                'opened_at' => $i->opened_at?->toIso8601String(),
                'acknowledged_at' => $i->acknowledged_at?->toIso8601String(),
                'resolved_at' => $i->resolved_at?->toIso8601String(),
            ])->values()->all(),
            'summary' => [
                'open' => (int) AdminEscalationIncident::query()->where('status', 'open')->count(),
                'acknowledged' => (int) AdminEscalationIncident::query()->where('status', 'acknowledged')->count(),
                'resolved' => (int) AdminEscalationIncident::query()->where('status', 'resolved')->count(),
                'critical' => (int) AdminEscalationIncident::query()->where('severity', 'critical')->where('status', '!=', 'resolved')->count(),
                'mtta_minutes' => $this->mttaMinutes(),
                'mttr_minutes' => $this->mttrMinutes(),
                'reopened_30d' => (int) DB::table('admin_escalation_events')
                    ->where('event_type', 'incident.reopened')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
            ],
            'staff_users' => User::query()
                ->whereNull('deleted_at')
                ->orderBy('email')
                ->limit(400)
                ->get(['id', 'email'])
                ->map(static fn (User $u): array => [
                    'id' => $u->id,
                    'email' => $u->email ?? ('User #'.$u->id),
                ])->values()->all(),
        ]);
    }

    private function mttaMinutes(): int
    {
        $value = DB::table('admin_escalation_incidents')
            ->whereNotNull('acknowledged_at')
            ->whereNotNull('opened_at')
            ->where('opened_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, opened_at, acknowledged_at)) as avg_minutes')
            ->value('avg_minutes');

        return max(0, (int) round((float) ($value ?? 0)));
    }

    private function mttrMinutes(): int
    {
        $value = DB::table('admin_escalation_incidents')
            ->whereNotNull('resolved_at')
            ->whereNotNull('opened_at')
            ->where('opened_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, opened_at, resolved_at)) as avg_minutes')
            ->value('avg_minutes');

        return max(0, (int) round((float) ($value ?? 0)));
    }
}
