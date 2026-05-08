<?php

declare(strict_types=1);

namespace App\Domain\Queries\Orders;

final readonly class OrderListQuery
{
    public function __construct(
        public int $viewerUserId,
        public bool $viewerIsPlatformStaff,
        public bool $sellerOnly = false,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
