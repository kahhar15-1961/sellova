<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\SellerProfile;
use App\Models\StaffUser;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WebNotificationCenterTest extends TestCase
{
    public function test_notifications_are_filtered_by_authenticated_role_context(): void
    {
        $user = $this->seedUserWithSellerProfile('panel-user');
        $otherUser = $this->seedUserWithSellerProfile('other-user');

        $this->seedNotification($user->id, Notification::ROLE_BUYER, 'buyer.order.completed', '/notifications');
        $this->seedNotification($user->id, Notification::ROLE_SELLER, 'seller.order.received', '/seller/orders');
        $this->seedNotification($otherUser->id, Notification::ROLE_BUYER, 'buyer.hidden', '/notifications');

        self::assertSame(1, Notification::query()->forPanel($user->id, Notification::ROLE_BUYER)->count());
        self::assertSame(1, Notification::query()->forPanel($user->id, Notification::ROLE_SELLER)->count());

        $buyerResponse = $this->actingAs($user, 'web')
            ->getJson('/web/actions/notifications?role=buyer&per_page=10');

        $buyerResponse->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.role', 'buyer')
            ->assertJsonPath('notifications.0.type', 'buyer.order.completed')
            ->assertJsonPath('unread_count', 1);

        $sellerResponse = $this->actingAs($user, 'web')
            ->getJson('/web/actions/notifications?role=seller&per_page=10');

        $sellerResponse->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.role', 'seller')
            ->assertJsonPath('notifications.0.type', 'seller.order.received')
            ->assertJsonPath('unread_count', 1);
    }

    public function test_mark_all_as_read_only_updates_the_requested_role_bucket(): void
    {
        $user = $this->seedUserWithSellerProfile('read-user');
        $buyerNotification = $this->seedNotification($user->id, Notification::ROLE_BUYER, 'buyer.funds.secured', '/notifications');
        $sellerNotification = $this->seedNotification($user->id, Notification::ROLE_SELLER, 'seller.dispute.opened', '/seller/disputes');

        $this->actingAs($user, 'web')
            ->withSession(['_token' => 'notif-token'])
            ->withHeader('X-CSRF-TOKEN', 'notif-token')
            ->postJson('/web/actions/notifications/mark-all-read', ['role' => 'seller'])
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertDatabaseHas('notifications', [
            'id' => $sellerNotification->id,
            'status' => 'read',
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => $buyerNotification->id,
            'status' => 'sent',
            'read_at' => null,
        ]);
    }

    private function seedUserWithSellerProfile(string $prefix): StaffUser
    {
        $user = StaffUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => $prefix.'-'.Str::random(6).'@example.test',
            'password_hash' => 'hash',
            'status' => 'active',
            'risk_level' => 'low',
        ]);

        SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'display_name' => Str::headline($prefix),
            'country_code' => 'BD',
            'default_currency' => 'BDT',
            'verification_status' => 'verified',
            'store_status' => 'active',
        ]);

        return $user;
    }

    private function seedNotification(int $userId, string $role, string $type, string $href): Notification
    {
        return Notification::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'user_role' => $role,
            'channel' => 'in_app',
            'template_code' => $type,
            'type' => $type,
            'title' => Str::headline(str_replace(['.', '_'], ' ', $type)),
            'message' => 'Notification body',
            'action_url' => $href,
            'payload_json' => [
                'title' => Str::headline(str_replace(['.', '_'], ' ', $type)),
                'body' => 'Notification body',
                'href' => $href,
                'role' => $role,
            ],
            'metadata_json' => [
                'title' => Str::headline(str_replace(['.', '_'], ' ', $type)),
                'body' => 'Notification body',
                'href' => $href,
                'role' => $role,
            ],
            'status' => 'sent',
            'sent_at' => now(),
            'read_at' => null,
        ]);
    }
}
