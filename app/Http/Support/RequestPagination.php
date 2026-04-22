<?php

declare(strict_types=1);

namespace App\Http\Support;

use Symfony\Component\HttpFoundation\Request;

final class RequestPagination
{
    /**
     * @return array{page: int, per_page: int}
     */
    public static function pageAndPerPage(Request $request, int $defaultPerPage = 20): array
    {
        $page = (int) ($request->query->get('page') ?? 1);
        $perPage = (int) ($request->query->get('per_page') ?? $defaultPerPage);

        return [
            'page' => max(1, $page),
            'per_page' => min(100, max(1, $perPage)),
        ];
    }

    public static function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $i = (int) $value;

        return $i > 0 ? $i : null;
    }
}
