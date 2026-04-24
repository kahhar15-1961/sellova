<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS for browser clients (e.g. Flutter web). Non-browser clients omit {@code Origin}; then no headers are added.
 *
 * Set {@code CORS_ALLOWED_ORIGINS} to a comma-separated list of exact origins (production).
 * When unset, {@code http(s)://localhost}, {@code 127.0.0.1}, and {@code [::1]} with any port are allowed.
 */
final class Cors
{
    public static function apply(Request $request, Response $response): Response
    {
        $origin = self::allowedOrigin($request);
        if ($origin === null) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        self::mergeVaryOrigin($response);

        return $response;
    }

    public static function preflightResponse(Request $request): Response
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $origin = self::allowedOrigin($request);
        if ($origin === null) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        self::mergeVaryOrigin($response);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, OPTIONS');
        $requested = $request->headers->get('Access-Control-Request-Headers');
        $response->headers->set(
            'Access-Control-Allow-Headers',
            ($requested !== null && $requested !== '')
                ? $requested
                : 'Content-Type, Accept, Authorization',
        );
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }

    private static function allowedOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null || $origin === '') {
            return null;
        }

        $explicit = getenv('CORS_ALLOWED_ORIGINS');
        if ($explicit !== false && trim((string) $explicit) !== '') {
            $list = array_map(trim(...), explode(',', (string) $explicit));

            return in_array($origin, $list, true) ? $origin : null;
        }

        if (preg_match(
            '#^https?://(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$#',
            $origin,
        ) === 1) {
            return $origin;
        }

        return null;
    }

    private static function mergeVaryOrigin(Response $response): void
    {
        $vary = $response->headers->get('Vary');
        if ($vary === null || $vary === '') {
            $response->headers->set('Vary', 'Origin');

            return;
        }

        $parts = array_map(trim(...), explode(',', $vary));
        if (! in_array('Origin', $parts, true)) {
            $parts[] = 'Origin';
            $response->headers->set('Vary', implode(', ', $parts));
        }
    }
}
