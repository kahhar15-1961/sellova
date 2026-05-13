<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class FinalMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('FinalMarketplaceSeeder is disabled in the production environment.');

            return;
        }

        LocalAppSeeder::seedAll();
        PromotionSeeder::seedDefaults();
        $this->call(MarketplaceTrustProfileSeeder::class);

        $this->command?->newLine();
        $this->command?->info('Final marketplace dataset seeded successfully.');
        $this->command?->info('Password for all accounts: '.LocalAppSeeder::PASSWORD_PLAIN);
        foreach (LocalAppSeeder::credentialsSummary()['emails'] as $line) {
            $this->command?->line("  • {$line}");
        }
    }
}
