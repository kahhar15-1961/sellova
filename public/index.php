<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../app/Http/Foundation/global_helpers.php';

use App\Http\Application;
use App\Http\Cors;
use App\Http\Foundation\EloquentBootstrap;
use App\Http\HttpKernel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$request = Request::createFromGlobals();

// CORS preflight must not depend on DB bootstrap (otherwise browsers see 503 + no CORS on OPTIONS).
if ($request->getMethod() === 'OPTIONS' && str_starts_with($request->getPathInfo(), '/api/')) {
    Cors::preflightResponse($request)->send();
    exit;
}

if (! EloquentBootstrap::bootFromEnvironment()) {
    $response = new JsonResponse([
        'error' => 'service_unavailable',
        'message' => 'Database is not configured. Set DB_HOST, DB_DATABASE, DB_USERNAME (or mirror TEST_DB_* for local parity).',
    ], Response::HTTP_SERVICE_UNAVAILABLE);
    Cors::apply($request, $response)->send();
    exit;
}

$app = new Application();
$routeFactory = require __DIR__.'/../routes/api.php';
$routes = $routeFactory($app);
$kernel = new HttpKernel($app, $routes);

$response = $kernel->handle($request);
$response->send();
