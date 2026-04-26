<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Admin\CommsDeliveryService;
use Illuminate\Console\Command;

final class RetryAdminCommsDeliveriesCommand extends Command
{
    protected $signature = 'admin:comms-retries';

    protected $description = 'Retry pending/failed admin comms deliveries due for retry.';

    public function handle(CommsDeliveryService $service): int
    {
        $count = $service->retryDueLogs();
        $this->info("Comms delivery logs processed: {$count}");

        return self::SUCCESS;
    }
}
