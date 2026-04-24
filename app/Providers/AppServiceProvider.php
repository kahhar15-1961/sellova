<?php

declare(strict_types=1);

namespace App\Providers;

use App\Admin\AdminPermission;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('admin-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        foreach (AdminPermission::all() as $code) {
            Gate::define($code, static fn (\App\Models\User $user): bool => $user->hasPermissionCode($code));
        }
    }
}
