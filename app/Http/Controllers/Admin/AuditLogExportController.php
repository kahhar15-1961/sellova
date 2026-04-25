<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditLogExportController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $q = trim((string) $request->query('q', ''));
        $actor = trim((string) $request->query('actor', ''));

        $builder = AuditLog::query()->with(['actor_user:id,email'])->orderByDesc('id');
        if ($actor !== '') {
            $builder->whereHas('actor_user', static function ($uq) use ($actor): void {
                $uq->where('email', 'like', '%'.$actor.'%');
            });
        }
        if ($q !== '') {
            $builder->where(function ($w) use ($q): void {
                $w->where('action', 'like', '%'.$q.'%')
                    ->orWhere('target_type', 'like', '%'.$q.'%')
                    ->orWhere('correlation_id', 'like', '%'.$q.'%')
                    ->orWhere('id', $q);
            });
        }

        return response()->streamDownload(function () use ($builder): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['id', 'created_at', 'actor_email', 'action', 'target_type', 'target_id', 'reason_code', 'correlation_id', 'ip_address']);
            foreach ($builder->cursor() as $log) {
                fputcsv($out, [
                    $log->id,
                    $log->created_at?->toIso8601String(),
                    $log->actor_user?->email,
                    $log->action,
                    $log->target_type,
                    $log->target_id,
                    $log->reason_code,
                    $log->correlation_id,
                    $log->ip_address,
                ]);
            }
            fclose($out);
        }, 'audit-logs.csv', ['Content-Type' => 'text/csv']);
    }
}
