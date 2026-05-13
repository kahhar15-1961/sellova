<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Auth\RoleCodes;
use App\Models\StaffUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class SellerVerificationFlowTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     */
    private function postWithCsrf(string $uri, array $data = []): TestResponse
    {
        $token = 'seller-verification-test-token';

        return $this->withSession(['_token' => $token])
            ->post($uri, ['_token' => $token, ...$data]);
    }

    private function seedSuperAdminRoleId(): int
    {
        $now = now();
        DB::table('roles')->updateOrInsert(
            ['code' => RoleCodes::SuperAdmin],
            ['name' => 'Super administrator', 'created_at' => $now, 'updated_at' => $now],
        );

        return (int) DB::table('roles')->where('code', RoleCodes::SuperAdmin)->value('id');
    }

    /**
     * @return array{admin: StaffUser, kycId: int}
     */
    private function seedSubmittedKycCase(): array
    {
        $now = now();
        $roleId = $this->seedSuperAdminRoleId();

        $adminUserId = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'email' => 'kyc-admin-'.Str::random(8).'@example.com',
            'phone' => null,
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'status' => 'active',
            'risk_level' => 'low',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $adminUserId,
            'role_id' => $roleId,
            'assigned_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sellerUserId = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'email' => 'kyc-seller-'.Str::random(8).'@example.com',
            'phone' => null,
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'status' => 'active',
            'risk_level' => 'low',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $sellerProfileId = DB::table('seller_profiles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'user_id' => $sellerUserId,
            'display_name' => 'Test Seller Co',
            'legal_name' => 'Test Seller LLC',
            'country_code' => 'US',
            'default_currency' => 'USD',
            'verification_status' => 'pending',
            'store_status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $kycId = DB::table('kyc_verifications')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'seller_profile_id' => $sellerProfileId,
            'status' => 'submitted',
            'provider_ref' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'submitted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $admin = StaffUser::query()->findOrFail($adminUserId);

        return ['admin' => $admin, 'kycId' => $kycId];
    }

    public function test_workspace_requires_authentication(): void
    {
        ['kycId' => $kycId] = $this->seedSubmittedKycCase();

        $this->get(route('admin.sellers.kyc.show', ['kyc' => $kycId]))
            ->assertRedirect();
    }

    public function test_claim_then_approve_writes_audit_and_updates_seller(): void
    {
        ['admin' => $admin, 'kycId' => $kycId] = $this->seedSubmittedKycCase();

        $this->actingAs($admin, 'web');

        $this->postWithCsrf(route('admin.sellers.kyc.claim', ['kyc' => $kycId]))
            ->assertRedirect();

        $this->assertDatabaseHas('kyc_verifications', [
            'id' => $kycId,
            'status' => 'under_review',
        ]);

        $this->postWithCsrf(route('admin.sellers.kyc.review', ['kyc' => $kycId]), [
                'decision' => 'approved',
                'reason' => null,
            ])
            ->assertRedirect(route('admin.sellers.kyc.show', ['kyc' => $kycId]));

        $this->assertDatabaseHas('kyc_verifications', [
            'id' => $kycId,
            'status' => 'approved',
        ]);

        $sellerProfileId = (int) DB::table('kyc_verifications')->where('id', $kycId)->value('seller_profile_id');
        $this->assertDatabaseHas('seller_profiles', [
            'id' => $sellerProfileId,
            'verification_status' => 'verified',
        ]);

        $this->assertGreaterThanOrEqual(1, DB::table('audit_logs')->where('target_type', 'kyc_verification')->where('target_id', $kycId)->count());
    }

    public function test_idempotent_approve_replay(): void
    {
        ['admin' => $admin, 'kycId' => $kycId] = $this->seedSubmittedKycCase();

        $this->actingAs($admin, 'web');
        $this->postWithCsrf(route('admin.sellers.kyc.claim', ['kyc' => $kycId]));
        $this->postWithCsrf(route('admin.sellers.kyc.review', ['kyc' => $kycId]), [
            'decision' => 'approved',
        ]);

        $countAfterFirst = DB::table('audit_logs')->where('target_type', 'kyc_verification')->where('target_id', $kycId)->count();

        $this->postWithCsrf(route('admin.sellers.kyc.review', ['kyc' => $kycId]), [
                'decision' => 'approved',
            ])
            ->assertRedirect(route('admin.sellers.kyc.show', ['kyc' => $kycId]));

        $this->assertSame($countAfterFirst, DB::table('audit_logs')->where('target_type', 'kyc_verification')->where('target_id', $kycId)->count());
    }
}
