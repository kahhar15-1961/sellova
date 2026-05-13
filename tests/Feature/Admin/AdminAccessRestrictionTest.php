<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SellerProfile;
use App\Models\StaffUser;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminAccessRestrictionTest extends TestCase
{
    public function test_buyer_cannot_access_admin_dashboard_and_sees_premium_restricted_page(): void
    {
        $buyer = StaffUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'buyer-access@example.test',
            'display_name' => 'Buyer Access',
            'password_hash' => password_hash('secret1234', PASSWORD_BCRYPT),
            'status' => 'active',
            'risk_level' => 'low',
        ]);

        $response = $this->actingAs($buyer, 'web')->get('/admin/dashboard');

        $response->assertForbidden();
        $response->assertSee('Error\/Status', false);
        $response->assertSee('&quot;status&quot;:403', false);
        $response->assertSee('&quot;home_href&quot;:&quot;\/dashboard&quot;', false);
    }

    public function test_seller_cannot_access_admin_login_and_is_redirected_to_seller_dashboard(): void
    {
        $sellerUser = StaffUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'seller-access@example.test',
            'display_name' => 'Seller Access',
            'password_hash' => password_hash('secret1234', PASSWORD_BCRYPT),
            'status' => 'active',
            'risk_level' => 'low',
        ]);

        SellerProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => (int) $sellerUser->id,
            'display_name' => 'Seller Access',
            'legal_name' => 'Seller Access LLC',
            'country_code' => 'BD',
            'default_currency' => 'BDT',
            'verification_status' => 'verified',
            'store_status' => 'active',
        ]);

        $response = $this->actingAs($sellerUser, 'web')->get('/admin/login');

        $response->assertRedirect('/seller/dashboard');
    }
}
