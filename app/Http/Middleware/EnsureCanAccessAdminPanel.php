<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Admin\AdminAuthorizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCanAccessAdminPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! AdminAuthorizer::canAccessPanel($user)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to access the admin panel.');
        }

        return $next($request);
    }
}
