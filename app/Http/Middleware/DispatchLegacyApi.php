<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\AppServices;
use App\Http\Cors;
use App\Http\HttpKernel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;

/**
 * Delegates {@code /api/*} and {@code /health} to the existing Symfony-routed JSON API
 * so mobile clients remain unchanged while the Laravel stack serves the admin UI.
 */
final class DispatchLegacyApi
{
    private static ?HttpKernel $kernel = null;

    private static ?RouteCollection $routes = null;

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        if ($path === 'health' || str_starts_with($path, 'api/')) {
            if ($request->getMethod() === 'OPTIONS' && str_starts_with($request->getPathInfo(), '/api/')) {
                return Cors::preflightResponse($request);
            }

            if (self::$kernel === null || self::$routes === null) {
                $services = new AppServices();
                $routeFactory = require base_path('routes/api.php');
                self::$routes = $routeFactory($services);
                self::$kernel = new HttpKernel($services, self::$routes);
            }

            return Cors::apply($request, self::$kernel->handle($request));
        }

        return $next($request);
    }
}
