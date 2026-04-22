<?php

declare(strict_types=1);

namespace App\Domain\Queries\Withdrawals;

final readonly class WithdrawalListQuery
{
    public function __construct(
        public int $viewerUserId,
        public bool $viewerIsPlatformStaff,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
