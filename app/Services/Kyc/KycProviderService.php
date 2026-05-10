<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use App\Models\KycVerificationLog;
use App\Models\KycVerificationProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KycProviderService
{
    /**
     * @return array{provider: KycVerificationProvider, session_id: string, session_url: string}
     */
    public function createSession(KycVerification $kyc): array
    {
        $provider = $this->activeProvider();
        $sessionId = 'kyc_'.$provider->code.'_'.Str::lower(Str::random(24));
        $sessionUrl = url('/seller/kyc?step=third-party&session='.$sessionId);

        $kyc->forceFill([
            'provider_id' => $provider->id,
            'provider_ref' => $kyc->provider_ref ?: 'seller-kyc-'.$kyc->seller_profile_id.'-'.Str::lower(Str::random(8)),
            'provider_session_id' => $sessionId,
            'provider_session_url' => $sessionUrl,
        ])->save();

        KycVerificationLog::query()->create([
            'uuid' => (string) Str::uuid(),
            'kyc_verification_id' => $kyc->id,
            'provider_id' => $provider->id,
            'direction' => 'outbound',
            'event_type' => 'session.created',
            'signature_status' => 'not_required',
            'payload_json' => [
                'provider' => $provider->code,
                'kyc_id' => $kyc->id,
                'session_id' => $sessionId,
            ],
            'response_json' => [
                'session_url' => $sessionUrl,
                'mode' => $provider->mode,
            ],
        ]);

        return ['provider' => $provider, 'session_id' => $sessionId, 'session_url' => $sessionUrl];
    }

    /**
     * @return array{ok: bool, status: string, kyc_id: int|null}
     */
    public function handleWebhook(string $providerCode, Request $request): array
    {
        $provider = KycVerificationProvider::query()->where('code', $providerCode)->first();
        if (! $provider instanceof KycVerificationProvider) {
            return ['ok' => false, 'status' => 'unknown_provider', 'kyc_id' => null];
        }

        $raw = $request->getContent();
        $signatureStatus = $this->verifySignature($provider, $raw, (string) $request->headers->get('X-KYC-Signature', ''))
            ? 'valid'
            : 'invalid';
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            $payload = $request->all();
        }

        $sessionId = (string) ($payload['session_id'] ?? $payload['verification_session_id'] ?? '');
        $kyc = $sessionId !== ''
            ? KycVerification::query()->where('provider_session_id', $sessionId)->first()
            : null;

        KycVerificationLog::query()->create([
            'uuid' => (string) Str::uuid(),
            'kyc_verification_id' => $kyc?->id,
            'provider_id' => $provider->id,
            'direction' => 'inbound',
            'event_type' => (string) ($payload['event'] ?? $payload['type'] ?? 'verification.updated'),
            'signature_status' => $signatureStatus,
            'payload_json' => $payload,
            'response_json' => null,
        ]);

        if ($signatureStatus !== 'valid' || ! $kyc instanceof KycVerification) {
            return ['ok' => false, 'status' => $signatureStatus !== 'valid' ? 'invalid_signature' : 'kyc_not_found', 'kyc_id' => $kyc?->id];
        }

        $providerStatus = strtolower((string) ($payload['status'] ?? $payload['verification_status'] ?? 'pending'));
        $target = match ($providerStatus) {
            'verified', 'approved', 'completed', 'success' => 'under_review',
            'rejected', 'failed', 'declined' => 'resubmission_required',
            default => 'third_party_pending',
        };

        DB::transaction(function () use ($kyc, $payload, $target): void {
            $before = (string) $kyc->status;
            $kyc->forceFill([
                'status' => $target,
                'provider_result_json' => $payload,
                'risk_level' => $payload['risk_level'] ?? $payload['risk'] ?? null,
            ])->save();
            $this->recordHistory($kyc, $before, $target, null, 'provider_webhook', 'Third-party verification webhook processed.');
        });

        return ['ok' => true, 'status' => $target, 'kyc_id' => (int) $kyc->id];
    }

    public function activeProvider(): KycVerificationProvider
    {
        $provider = KycVerificationProvider::query()->where('is_active', true)->orderBy('id')->first();
        if ($provider instanceof KycVerificationProvider) {
            return $provider;
        }

        return KycVerificationProvider::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'mock',
            'name' => 'Internal Mock Verification',
            'mode' => 'mock',
            'is_active' => true,
            'config_json' => [],
            'webhook_secret_encrypted' => 'local-kyc-webhook-secret',
        ]);
    }

    public function verifySignature(KycVerificationProvider $provider, string $payload, string $signature): bool
    {
        $secret = (string) ($provider->webhook_secret_encrypted ?: '');
        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    public function recordHistory(KycVerification $kyc, ?string $from, string $to, ?int $actorUserId, ?string $reason, ?string $note = null): void
    {
        \App\Models\KycStatusHistory::query()->create([
            'uuid' => (string) Str::uuid(),
            'kyc_verification_id' => $kyc->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actorUserId,
            'reason_code' => $reason,
            'note' => $note,
            'metadata_json' => [],
        ]);
    }
}
