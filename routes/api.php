<?php

declare(strict_types=1);

use App\Http\AppServices;
use App\Http\Routing\ApiRouteRegistrar;
use Symfony\Component\Routing\RouteCollection;

return static function (AppServices $app): RouteCollection {
    $routes = new RouteCollection();
    ApiRouteRegistrar::register($routes, $app);

    return $routes;
};
