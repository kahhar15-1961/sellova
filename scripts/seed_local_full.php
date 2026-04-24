<?php

declare(strict_types=1);

/**
 * Truncate core tables and seed a full local dataset (users, storefronts, products,
 * orders, escrow, disputes, withdrawals, wallets).
 *
 * Usage (same DB_* env as public/index.php):
 *
 *   cd /path/to/sellova
 *   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=db_sellova DB_USERNAME=root DB_PASSWORD=root php scripts/seed_local_full.php
 *
 * Or: composer seed:local
 *
 * @see \Database\Seeders\LocalAppSeeder
 */

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../app/Http/Foundation/global_helpers.php';

use App\Http\Foundation\EloquentBootstrap;
use Database\Seeders\LocalAppSeeder;

if (! EloquentBootstrap::bootFromEnvironment()) {
    fwrite(STDERR, "Database bootstrap failed. Set DB_HOST, DB_DATABASE, DB_USERNAME (and DB_PASSWORD).\n");
    exit(1);
}

LocalAppSeeder::seedAll();

echo "Local full seed complete.\n\n";
echo 'Login password for all accounts: '.LocalAppSeeder::PASSWORD_PLAIN."\n\n";
echo "Accounts:\n";
foreach (LocalAppSeeder::credentialsSummary()['emails'] as $line) {
    echo "  - {$line}\n";
}
