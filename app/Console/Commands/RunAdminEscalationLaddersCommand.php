<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminEscalationIncident;
use App\Services\Admin\EscalationOperationsService;
use Illuminate\Console\Command;

final class RunAdminEscalationLaddersCommand extends Command
{
    protected $signature = 'admin:escalation-ladders';

    protected $description = 'Advance escalation ladder stages for overdue unacknowledged incidents.';

    public function handle(EscalationOperationsService $ops): int
    {
        $incidents = AdminEscalationIncident::query()
            ->where('status', 'open')
            ->whereNotNull('next_ladder_at')
            ->where('next_ladder_at', '<=', now())
            ->orderBy('next_ladder_at')
            ->limit(200)
            ->get();

        $advanced = 0;
        foreach ($incidents as $incident) {
            if ($ops->advanceLadder($incident)) {
                $advanced++;
            }
        }

        $this->info("Escalation ladder advanced incidents: {$advanced}");

        return self::SUCCESS;
    }
}
