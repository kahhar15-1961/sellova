<?php

declare(strict_types=1);

/**
 * Idempotent dev seed: platform roles, admin/adjudicator users, buyer, seller + profile.
 *
 * Usage (same DB_* / TEST_DB_* env as public/index.php):
 *
 *   cd /path/to/sellova
 *   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=db_sellova DB_USERNAME=root DB_PASSWORD=root php scripts/seed_dev_data.php
 *
 * Default password for all seeded accounts: secret1234
 *
 * Accounts:
 *   - dev-admin@sellova.local     — role admin
 *   - dev-adjudicator@sellova.local — role adjudicator
 *   - dev-buyer@sellova.local     — buyer (no seller profile)
 *   - dev-seller@sellova.local    — seller profile (unverified)
 *
 * Also seeds demo catalog, orders, escrow, one dispute, and a withdrawal request
 * (see scripts/seed_dev_demo_content.php).
 */

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../app/Http/Foundation/global_helpers.php';

use App\Auth\RoleCodes;
use App\Http\Foundation\EloquentBootstrap;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Str;

if (! EloquentBootstrap::bootFromEnvironment()) {
    fwrite(STDERR, "Database bootstrap failed. Set DB_HOST, DB_DATABASE, DB_USERNAME (and DB_PASSWORD).\n");
    exit(1);
}

$devPassword = 'secret1234';
$passwordHash = password_hash($devPassword, PASSWORD_DEFAULT);

$assignRole = static function (User $user, string $roleCode): void {
    $role = Role::query()->firstOrCreate(
        ['code' => $roleCode],
        ['name' => ucfirst($roleCode)],
    );
    UserRole::query()->firstOrCreate(
        ['user_id' => $user->id, 'role_id' => $role->id],
        ['assigned_by' => null],
    );
};

$ensureUser = static function (string $email) use ($passwordHash): User {
    $user = User::query()->firstOrCreate(
        ['email' => $email],
        [
            'uuid' => (string) Str::uuid(),
            'phone' => null,
            'password_hash' => $passwordHash,
            'status' => 'active',
            'risk_level' => 'low',
        ],
    );
    $user->forceFill([
        'password_hash' => $passwordHash,
        'status' => 'active',
        'risk_level' => 'low',
    ])->save();

    return $user;
};

// Core roles used by DomainGate / RBAC
Role::query()->firstOrCreate(
    ['code' => RoleCodes::Admin],
    ['name' => 'Administrator'],
);
Role::query()->firstOrCreate(
    ['code' => RoleCodes::Adjudicator],
    ['name' => 'Adjudicator'],
);

$admin = $ensureUser('dev-admin@sellova.local');
$assignRole($admin, RoleCodes::Admin);

$adjudicator = $ensureUser('dev-adjudicator@sellova.local');
$assignRole($adjudicator, RoleCodes::Adjudicator);

$buyer = $ensureUser('dev-buyer@sellova.local');

$sellerUser = $ensureUser('dev-seller@sellova.local');
SellerProfile::query()->firstOrCreate(
    ['user_id' => $sellerUser->id],
    [
        'uuid' => (string) Str::uuid(),
        'display_name' => 'Dev Seller',
        'legal_name' => 'Dev Seller LLC',
        'country_code' => 'US',
        'default_currency' => 'USD',
        'verification_status' => 'unverified',
        'store_status' => 'active',
    ],
);

echo "Seed complete.\n\n";
echo "Password for all accounts: {$devPassword}\n\n";
echo "| Email                          | Notes\n";
echo "|--------------------------------|--------------------------------\n";
echo "| dev-admin@sellova.local        | Platform admin (withdrawals, disputes, etc.)\n";
echo "| dev-adjudicator@sellova.local  | Adjudicator role\n";
echo "| dev-buyer@sellova.local        | Buyer only\n";
echo "| dev-seller@sellova.local       | Seller profile (use seller flows)\n";

require __DIR__.'/seed_dev_demo_content.php';
run_dev_demo_content();
