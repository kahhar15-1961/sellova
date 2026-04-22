<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Http\Application;
use App\Http\Foundation\EloquentBootstrap;
use App\Http\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

if (! EloquentBootstrap::bootFromEnvironment()) {
    http_response_code(503);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'error' => 'service_unavailable',
        'message' => 'Database is not configured. Set DB_HOST, DB_DATABASE, DB_USERNAME (or mirror TEST_DB_* for local parity).',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$app = new Application();
$routeFactory = require __DIR__.'/../routes/api.php';
$routes = $routeFactory($app);
$kernel = new HttpKernel($app, $routes);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
