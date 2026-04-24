<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\ExceptionToHttpMapper;
use App\Http\Middleware\ParseJsonBody;
use App\Http\Middleware\ResolveActorUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

final class HttpKernel
{
    public function __construct(
        private readonly Application $app,
        private readonly RouteCollection $routes,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $parse = new ParseJsonBody();
            $early = $parse($request);
            if ($early instanceof Response) {
                return Cors::apply($request, $early);
            }

            if ($request->getMethod() === 'OPTIONS' && str_starts_with($request->getPathInfo(), '/api/')) {
                return Cors::preflightResponse($request);
            }

            (new ResolveActorUser())($request);

            $context = (new RequestContext())->fromRequest($request);
            $matcher = new UrlMatcher($this->routes, $context);
            $match = $matcher->matchRequest($request);

            $needsAuth = (bool) ($match['_auth'] ?? false);
            $controller = $match['_controller'] ?? null;
            unset($match['_controller'], $match['_route'], $match['_auth']);

            foreach ($match as $key => $value) {
                $request->attributes->set((string) $key, $value);
            }

            if ($needsAuth) {
                $this->app->requireActor($request);
            }

            if (! is_callable($controller)) {
                throw new \RuntimeException('Route is missing a valid _controller.');
            }

            return Cors::apply($request, $controller($request));
        } catch (ResourceNotFoundException|MethodNotAllowedException $e) {
            return Cors::apply($request, ExceptionToHttpMapper::map($e));
        } catch (\Throwable $e) {
            return Cors::apply($request, ExceptionToHttpMapper::map($e));
        }
    }
}
