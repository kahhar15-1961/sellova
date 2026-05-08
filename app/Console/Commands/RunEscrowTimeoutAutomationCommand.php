<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TimeoutAutomation\TimeoutAutomationService;
use Illuminate\Console\Command;

final class RunEscrowTimeoutAutomationCommand extends Command
{
    protected $signature = 'escrow:timeouts {--limit=200}';

    protected $description = 'Process escrow timeout reminders, expirations, escalations, disputes, and auto-release rules.';

    public function handle(TimeoutAutomationService $timeouts): int
    {
        $result = $timeouts->processDue((int) $this->option('limit'));
        foreach ($result as $key => $count) {
            $this->line($key.': '.$count);
        }

        return self::SUCCESS;
    }
}

