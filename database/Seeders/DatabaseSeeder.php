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
        if (app()->environment('production')) {
            $this->command?->error('DatabaseSeeder is disabled in the production environment.');

            return;
        }

        LocalAppSeeder::seedAll();
        PromotionSeeder::seedDefaults();

        $this->command?->newLine();
        $this->command?->info('Local dataset seeded (core tables were truncated).');
        $this->command?->info('Password for all accounts: '.LocalAppSeeder::PASSWORD_PLAIN);
        foreach (LocalAppSeeder::credentialsSummary()['emails'] as $line) {
            $this->command?->line("  • {$line}");
        }
    }
}
