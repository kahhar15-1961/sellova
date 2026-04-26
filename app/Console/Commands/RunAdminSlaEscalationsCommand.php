<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Admin\SlaEscalationService;
use Illuminate\Console\Command;

final class RunAdminSlaEscalationsCommand extends Command
{
    protected $signature = 'admin:sla-escalations';

    protected $description = 'Escalate breached admin disputes/withdrawals and notify staff.';

    public function handle(SlaEscalationService $service): int
    {
        $result = $service->run();

        $this->info('SLA escalations complete.');
        $this->line('Disputes escalated: '.$result['disputes_escalated']);
        $this->line('Withdrawals escalated: '.$result['withdrawals_escalated']);

        return self::SUCCESS;
    }
}
