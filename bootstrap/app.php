<?php

declare(strict_types=1);

use App\Admin\AdminAuthorizer;
use App\Http\Middleware\DispatchLegacyApi;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        if (filter_var(env('TRUST_PROXIES', false), FILTER_VALIDATE_BOOLEAN)) {
            $raw = (string) env('TRUSTED_PROXIES', '*');
            $at = str_contains($raw, ',')
                ? array_values(array_filter(array_map(trim(...), explode(',', $raw))))
                : $raw;
            $middleware->trustProxies(
                at: $at,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
                    | Request::HEADER_X_FORWARDED_AWS_ELB,
            );
        }

        $middleware->prepend(DispatchLegacyApi::class);
        $middleware->redirectGuestsTo(static function (Request $request): string {
            return $request->is('admin') || $request->is('admin/*')
                ? '/admin/login'
                : '/login';
        });
        $middleware->redirectUsersTo(static function (Request $request): string {
            /** @var User|null $user */
            $user = $request->user();
            if ($user === null) {
                return '/';
            }

            if (AdminAuthorizer::canAccessPanel($user)) {
                return '/admin/dashboard';
            }

            if ($user->sellerProfile()->exists()) {
                return '/seller/dashboard';
            }

            return '/dashboard';
        });
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhooks/kyc/*',
        ]);
        $middleware->alias([
            'admin.panel' => \App\Http\Middleware\EnsureCanAccessAdminPanel::class,
            'admin.permission' => \App\Http\Middleware\EnsureAdminPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(static function (BaseResponse $response, \Throwable $exception, Request $request): BaseResponse {
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();
            if ($exception instanceof AuthorizationException) {
                $status = BaseResponse::HTTP_FORBIDDEN;
            } elseif ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
            }

            if (! in_array($status, [
                BaseResponse::HTTP_FORBIDDEN,
                BaseResponse::HTTP_NOT_FOUND,
                419,
                BaseResponse::HTTP_TOO_MANY_REQUESTS,
                BaseResponse::HTTP_INTERNAL_SERVER_ERROR,
            ], true)) {
                return $response;
            }

            $user = $request->user();
            $homeHref = '/';
            $searchHref = '/marketplace';
            if ($user instanceof User) {
                if (AdminAuthorizer::canAccessPanel($user)) {
                    $homeHref = '/admin/dashboard';
                    $searchHref = '/admin/dashboard';
                } elseif ($user->sellerProfile()->exists()) {
                    $homeHref = '/seller/dashboard';
                    $searchHref = '/seller/products';
                } else {
                    $homeHref = '/dashboard';
                    $searchHref = '/marketplace';
                }
            }

            return Inertia::render('Error/Status', [
                'status' => $status,
                'home_href' => $homeHref,
                'search_href' => $searchHref,
                'support_href' => $user instanceof User && $user->sellerProfile()->exists() ? '/seller/support' : '/support',
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
