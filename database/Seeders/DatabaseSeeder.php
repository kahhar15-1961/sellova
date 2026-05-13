<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Entry point for {@code php artisan db:seed}.
 *
 * Runs the same full reset + marketplace dataset as {@code composer seed:local}
 * ({@see LocalAppSeeder::seedAll()}). For idempotent dev-only accounts + demo rows
 * without truncating, use {@code composer seed:dev} instead.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FinalMarketplaceSeeder::class);
    }
}
