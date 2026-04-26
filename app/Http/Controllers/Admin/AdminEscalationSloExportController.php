<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminEscalationSloExportController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $days = (int) $request->query('days', 30);
        $days = max(1, min(365, $days));
        $from = now()->subDays($days);

        $rows = \DB::table('admin_escalation_incidents')
            ->selectRaw("
                queue_code,
                COUNT(*) as total_incidents,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_incidents,
                SUM(CASE WHEN status <> 'resolved' THEN 1 ELSE 0 END) as active_incidents,
                AVG(CASE WHEN acknowledged_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, opened_at, acknowledged_at) END) as avg_mtta_minutes,
                AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, opened_at, resolved_at) END) as avg_mttr_minutes
            ")
            ->where('opened_at', '>=', $from)
            ->groupBy('queue_code')
            ->orderBy('queue_code')
            ->get();

        $reopenMap = \DB::table('admin_escalation_events')
            ->join('admin_escalation_incidents as i', 'i.id', '=', 'admin_escalation_events.incident_id')
            ->selectRaw('i.queue_code, COUNT(*) as reopened_count')
            ->where('admin_escalation_events.event_type', 'incident.reopened')
            ->where('admin_escalation_events.created_at', '>=', $from)
            ->groupBy('i.queue_code')
            ->pluck('reopened_count', 'i.queue_code');

        return response()->streamDownload(static function () use ($rows, $reopenMap, $days): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['period_days', 'queue_code', 'total_incidents', 'resolved_incidents', 'active_incidents', 'avg_mtta_minutes', 'avg_mttr_minutes', 'reopened_count']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $days,
                    (string) $r->queue_code,
                    (int) $r->total_incidents,
                    (int) $r->resolved_incidents,
                    (int) $r->active_incidents,
                    (int) round((float) ($r->avg_mtta_minutes ?? 0)),
                    (int) round((float) ($r->avg_mttr_minutes ?? 0)),
                    (int) ($reopenMap[(string) $r->queue_code] ?? 0),
                ]);
            }
            fclose($out);
        }, sprintf('escalation-slo-%s.csv', now()->format('Ymd-His')), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
