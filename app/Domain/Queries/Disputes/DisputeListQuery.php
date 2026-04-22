<?php

declare(strict_types=1);

namespace App\Domain\Queries\Disputes;

final readonly class DisputeListQuery
{
    public function __construct(
        public int $viewerUserId,
        public bool $viewerIsPlatformStaff,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
