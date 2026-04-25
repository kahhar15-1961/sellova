<?php

declare(strict_types=1);

namespace App\Providers;

use App\Admin\AdminPermission;
use App\Models\DisputeCase;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::bind('withdrawal', static fn (string $value): WithdrawalRequest => WithdrawalRequest::query()->findOrFail($value));
        Route::bind('dispute', static fn (string $value): DisputeCase => DisputeCase::query()->findOrFail($value));

        RateLimiter::for('admin-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        foreach (AdminPermission::all() as $code) {
            Gate::define($code, static fn (User $user): bool => $user->hasPermissionCode($code));
        }
    }
}
