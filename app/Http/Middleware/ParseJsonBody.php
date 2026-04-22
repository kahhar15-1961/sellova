<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ParseJsonBody
{
    public function __invoke(Request $request): ?Response
    {
        $contentType = (string) $request->headers->get('Content-Type', '');
        if (! str_contains(strtolower($contentType), 'application/json')) {
            return null;
        }

        $raw = $request->getContent();
        if ($raw === '' || $raw === '0') {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return new JsonResponse([
                'error' => 'invalid_json',
                'message' => 'Request body must be a JSON object.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $request->request->replace($data);

        return null;
    }
}
