<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminCommsDeliveryLog;
use App\Models\AdminCommsIntegration;
use App\Models\AdminEscalationIncident;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class CommsDeliveryService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function queueDelivery(
        AdminEscalationIncident $incident,
        AdminCommsIntegration $integration,
        string $eventType,
        array $payload,
    ): AdminCommsDeliveryLog {
        $log = AdminCommsDeliveryLog::query()->create([
            'incident_id' => $incident->id,
            'integration_id' => $integration->id,
            'event_type' => $eventType,
            'status' => 'pending',
            'attempt_count' => 0,
            'request_payload_json' => $payload,
            'next_retry_at' => now(),
        ]);

        $this->attemptDelivery($log);

        return $log;
    }

    public function attemptDelivery(AdminCommsDeliveryLog $log): void
    {
        $integration = $log->integration;
        if ($integration === null || ! $integration->is_enabled) {
            $log->forceFill([
                'status' => 'failed',
                'last_error' => 'Integration missing or disabled.',
                'attempt_count' => $log->attempt_count + 1,
                'next_retry_at' => null,
            ])->save();

            return;
        }

        try {
            if ($integration->channel === 'webhook' && $integration->webhook_url) {
                Http::timeout(8)->post($integration->webhook_url, $log->request_payload_json ?? [])->throw();
            } elseif ($integration->channel === 'email' && $integration->email_to) {
                Mail::raw(
                    (string) (($log->request_payload_json['message'] ?? null) ?: 'Sellova escalation notification'),
                    static function ($msg) use ($integration, $log): void {
                        $msg->to((string) $integration->email_to)
                            ->subject('Sellova escalation: '.$log->event_type);
                    },
                );
            } else {
                throw new \RuntimeException('Integration endpoint is not configured.');
            }

            $log->forceFill([
                'status' => 'sent',
                'attempt_count' => $log->attempt_count + 1,
                'delivered_at' => now(),
                'next_retry_at' => null,
                'last_error' => null,
            ])->save();

            $integration->forceFill(['last_tested_at' => now()])->save();
        } catch (Throwable $e) {
            $attempts = $log->attempt_count + 1;
            $shouldRetry = $attempts < 5;
            $delayMinutes = min(60, (int) pow(2, max(0, $attempts - 1)));
            $log->forceFill([
                'status' => $shouldRetry ? 'retrying' : 'failed',
                'attempt_count' => $attempts,
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                'next_retry_at' => $shouldRetry ? now()->addMinutes($delayMinutes) : null,
            ])->save();
        }
    }

    public function retryDueLogs(int $limit = 200): int
    {
        $logs = AdminCommsDeliveryLog::query()
            ->with('integration')
            ->whereIn('status', ['pending', 'retrying'])
            ->where(function ($q): void {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($logs as $log) {
            $this->attemptDelivery($log);
        }

        return $logs->count();
    }
}
