<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdatePushNotificationSettingsRequest;
use App\Http\Requests\Admin\SendPushNotificationTestRequest;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushNotification\PushNotificationSettingsService;
use App\Services\PushNotification\PushNotificationService;
use App\Services\TimeoutAutomation\EscrowTimeoutSettingsService;
use App\Services\Withdrawal\WithdrawalSettingsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

final class SettingsController extends AdminPageController
{
    public function __construct(
        private readonly PushNotificationSettingsService $pushSettings,
        private readonly PushNotificationService $pushService,
        private readonly EscrowTimeoutSettingsService $timeoutSettings = new EscrowTimeoutSettingsService(),
        private readonly WithdrawalSettingsService $withdrawalSettings = new WithdrawalSettingsService(),
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'header' => $this->pageHeader(
                'Settings',
                'Runtime snapshot plus push delivery controls for Firebase Cloud Messaging.',
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
            'push_settings' => $this->pushSettingsPayload(),
            'timeout_settings' => $this->timeoutSettingsPayload(),
            'withdrawal_settings' => $this->withdrawalSettingsPayload(),
        ]);
    }

    public function updatePush(Request $request): Response
    {
        $data = UpdatePushNotificationSettingsRequest::toArray($request);
        $settings = $this->pushSettings->update($data);

        return Inertia::render('Admin/Settings/Index', [
            'header' => $this->pageHeader(
                'Settings',
                'Runtime snapshot plus push delivery controls for Firebase Cloud Messaging.',
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
            'push_settings' => $this->pushSettingsPayload($settings),
            'timeout_settings' => $this->timeoutSettingsPayload(),
            'withdrawal_settings' => $this->withdrawalSettingsPayload(),
            'push_saved' => true,
        ]);
    }

    public function updateWithdrawals(Request $request): Response
    {
        $settings = $this->withdrawalSettings->update($request->all());

        return Inertia::render('Admin/Settings/Index', [
            'header' => $this->pageHeader(
                'Settings',
                'Runtime snapshot plus push delivery controls for Firebase Cloud Messaging.',
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
            'push_settings' => $this->pushSettingsPayload(),
            'timeout_settings' => $this->timeoutSettingsPayload(),
            'withdrawal_settings' => $this->withdrawalSettingsPayload($settings),
            'withdrawal_saved' => true,
        ]);
    }

    public function updateTimeouts(Request $request): Response
    {
        $actor = $request->user();
        $settings = $this->timeoutSettings->update($request->all(), $actor instanceof User ? (int) $actor->id : null);

        return Inertia::render('Admin/Settings/Index', [
            'header' => $this->pageHeader(
                'Settings',
                'Runtime snapshot plus push delivery controls for Firebase Cloud Messaging.',
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
            'push_settings' => $this->pushSettingsPayload(),
            'timeout_settings' => $this->timeoutSettingsPayload($settings),
            'withdrawal_settings' => $this->withdrawalSettingsPayload(),
            'timeout_saved' => true,
        ]);
    }

    public function testPush(Request $request): Response
    {
        $data = SendPushNotificationTestRequest::toArray($request);
        $actor = $request->user();

        $recipient = null;
        if (! empty($data['recipient_email'])) {
            $recipient = User::query()->where('email', $data['recipient_email'])->first();
        }
        if ($recipient === null && $actor instanceof User) {
            $recipient = $actor;
        }
        if ($recipient === null) {
            abort(404, 'Recipient not found.');
        }

        $title = trim((string) ($data['title'] ?? 'Sellova push test'));
        $body = trim((string) ($data['body'] ?? 'This is a test push from admin settings.'));

        $notification = Notification::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $recipient->id,
            'channel' => 'in_app',
            'template_code' => 'admin.push.test',
            'payload_json' => [
                'title' => $title,
                'body' => $body,
                'href' => '',
                'recipient_email' => $recipient->email,
            ],
            'status' => 'queued',
            'sent_at' => now(),
        ]);

        $this->pushService->sendToUser((int) $recipient->id, [
            'title' => $title,
            'body' => $body,
            'template_code' => 'admin.push.test',
            'recipient_email' => $recipient->email,
        ]);

        return Inertia::render('Admin/Settings/Index', [
            'header' => $this->pageHeader(
                'Settings',
                'Runtime snapshot plus push delivery controls for Firebase Cloud Messaging.',
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
            'push_settings' => $this->pushSettingsPayload(),
            'timeout_settings' => $this->timeoutSettingsPayload(),
            'withdrawal_settings' => $this->withdrawalSettingsPayload(),
            'push_tested' => true,
            'push_test_recipient' => $recipient->email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pushSettingsPayload(?\App\Models\PushNotificationSetting $settings = null): array
    {
        $settings ??= $this->pushSettings->current();
        return [
            'enabled' => $settings->enabled,
            'provider' => $settings->provider,
            'fcm_project_id' => $settings->fcm_project_id ?? '',
            'fcm_client_email' => $settings->fcm_client_email ?? '',
            'fcm_private_key' => $settings->fcm_private_key ?? '',
            'android_channel_id' => $settings->android_channel_id,
            'android_channel_name' => $settings->android_channel_name,
            'android_channel_description' => $settings->android_channel_description,
            'last_tested_at' => $settings->last_tested_at?->toIso8601String(),
        ];
    }

    private function timeoutSettingsPayload(?\App\Models\EscrowTimeoutSetting $settings = null): array
    {
        $settings ??= $this->timeoutSettings->current();

        return [
            'unpaid_order_expiration_minutes' => (int) $settings->unpaid_order_expiration_minutes,
            'unpaid_order_warning_minutes' => (int) ($settings->unpaid_order_warning_minutes ?? 10),
            'seller_fulfillment_deadline_hours' => (int) $settings->seller_fulfillment_deadline_hours,
            'seller_fulfillment_warning_hours' => (int) ($settings->seller_fulfillment_warning_hours ?? 2),
            'buyer_review_deadline_hours' => (int) $settings->buyer_review_deadline_hours,
            'buyer_review_reminder_1_hours' => (int) $settings->buyer_review_reminder_1_hours,
            'buyer_review_reminder_2_hours' => (int) $settings->buyer_review_reminder_2_hours,
            'escalation_warning_minutes' => (int) ($settings->escalation_warning_minutes ?? 60),
            'seller_min_fulfillment_hours' => (int) $settings->seller_min_fulfillment_hours,
            'seller_max_fulfillment_hours' => (int) $settings->seller_max_fulfillment_hours,
            'buyer_min_review_hours' => (int) $settings->buyer_min_review_hours,
            'buyer_max_review_hours' => (int) $settings->buyer_max_review_hours,
            'auto_escalation_after_review_expiry' => (bool) $settings->auto_escalation_after_review_expiry,
            'auto_cancel_unpaid_orders' => (bool) $settings->auto_cancel_unpaid_orders,
            'auto_release_after_buyer_timeout' => (bool) $settings->auto_release_after_buyer_timeout,
            'auto_create_dispute_on_timeout' => (bool) $settings->auto_create_dispute_on_timeout,
            'dispute_review_queue_enabled' => (bool) $settings->dispute_review_queue_enabled,
        ];
    }

    private function withdrawalSettingsPayload(?\App\Models\WithdrawalSetting $settings = null): array
    {
        $settings ??= $this->withdrawalSettings->current();

        return [
            'minimum_withdrawal_amount' => (string) $settings->minimum_withdrawal_amount,
            'currency' => (string) $settings->currency,
        ];
    }
}
