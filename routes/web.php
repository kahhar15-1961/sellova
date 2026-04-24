<?php

declare(strict_types=1);

use App\Admin\AdminPermission;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AuditLogsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DisputesController;
use App\Http\Controllers\Admin\EscrowsController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\SellerVerificationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\WalletsController;
use App\Http\Controllers\Admin\WithdrawalsController;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn () => redirect('/admin/dashboard'));

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::post('logout', [AdminAuthController::class, 'destroy'])->middleware('auth')->name('logout');

    Route::middleware(['auth', 'admin.panel'])->group(function (): void {
        Route::get('/', static fn () => redirect()->route('admin.dashboard'));
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('users', UsersController::class)
            ->name('users.index')
            ->middleware('admin.permission:'.AdminPermission::USERS_VIEW);

        Route::get('sellers/kyc/documents/{document}/download', [SellerVerificationController::class, 'downloadDocument'])
            ->name('sellers.kyc.documents.download')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VIEW);

        Route::post('sellers/kyc/{kyc}/claim', [SellerVerificationController::class, 'claim'])
            ->name('sellers.kyc.claim')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VERIFY);

        Route::post('sellers/kyc/{kyc}/review', [SellerVerificationController::class, 'review'])
            ->name('sellers.kyc.review')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VERIFY);

        Route::get('sellers/kyc/{kyc}', [SellerVerificationController::class, 'show'])
            ->name('sellers.kyc.show')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VIEW);

        Route::get('sellers', [SellerVerificationController::class, 'index'])
            ->name('sellers.index')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VIEW);

        Route::get('products', ProductsController::class)
            ->name('products.index')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_VIEW);

        Route::get('orders', OrdersController::class)
            ->name('orders.index')
            ->middleware('admin.permission:'.AdminPermission::ORDERS_VIEW);

        Route::get('escrows', EscrowsController::class)
            ->name('escrows.index')
            ->middleware('admin.permission:'.AdminPermission::ESCROWS_VIEW);

        Route::get('disputes', DisputesController::class)
            ->name('disputes.index')
            ->middleware('admin.permission:'.AdminPermission::DISPUTES_VIEW);

        Route::get('withdrawals', WithdrawalsController::class)
            ->name('withdrawals.index')
            ->middleware('admin.permission:'.AdminPermission::WITHDRAWALS_VIEW);

        Route::get('wallets', WalletsController::class)
            ->name('wallets.index')
            ->middleware('admin.permission:'.AdminPermission::WALLETS_VIEW);

        Route::get('settings', SettingsController::class)
            ->name('settings.index')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_VIEW);

        Route::get('audit-logs', AuditLogsController::class)
            ->name('audit-logs.index')
            ->middleware('admin.permission:'.AdminPermission::AUDIT_VIEW);
    });
});
