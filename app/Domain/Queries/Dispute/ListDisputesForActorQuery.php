<?php

declare(strict_types=1);

namespace App\Domain\Queries\Dispute;

final readonly class ListDisputesForActorQuery
{
    public function __construct(
        public int $actorUserId,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
