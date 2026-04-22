<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiEnvelope
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function data(mixed $data, int $status = Response::HTTP_OK, array $meta = []): JsonResponse
    {
        $body = ['data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $status);
    }

    public static function created(mixed $data): JsonResponse
    {
        return self::data($data, Response::HTTP_CREATED);
    }

    /**
     * @param  list<mixed>  $items
     * @param  array<string, mixed>  $extraMeta
     */
    public static function paginated(
        array $items,
        int $page,
        int $perPage,
        int $total,
        array $extraMeta = [],
    ): JsonResponse {
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));

        return self::data($items, Response::HTTP_OK, array_merge([
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ], $extraMeta));
    }

    public static function notImplemented(string $module, string $detail = ''): JsonResponse
    {
        return new JsonResponse([
            'error' => 'not_implemented',
            'message' => 'This API module is not implemented yet.',
            'module' => $module,
            'detail' => $detail,
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function error(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return new JsonResponse(array_merge([
            'error' => $code,
            'message' => $message,
        ], $extra), $status);
    }
}
