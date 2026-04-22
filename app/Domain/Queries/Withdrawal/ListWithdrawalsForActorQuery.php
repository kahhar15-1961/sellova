<?php

declare(strict_types=1);

namespace App\Domain\Queries\Withdrawal;

final readonly class ListWithdrawalsForActorQuery
{
    public function __construct(
        public int $actorUserId,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
