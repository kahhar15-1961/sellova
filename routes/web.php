<?php

declare(strict_types=1);

use App\Admin\AdminPermission;
use App\Http\Controllers\Admin\AdminActionApprovalController;
use App\Http\Controllers\Admin\AdminApprovalMessageController;
use App\Http\Controllers\Admin\AdminApprovalRealtimeController;
use App\Http\Controllers\Admin\AdminApprovalsInboxController;
use App\Http\Controllers\Admin\AdminCommsIntegrationsController;
use App\Http\Controllers\Admin\AdminEscalationIncidentActionController;
use App\Http\Controllers\Admin\AdminEscalationPoliciesController;
use App\Http\Controllers\Admin\AdminEscalationsInboxController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminRunbooksController;
use App\Http\Controllers\Admin\AuditLogExportController;
use App\Http\Controllers\Admin\AuditLogsController;
use App\Http\Controllers\Admin\AuditLogShowController;
use App\Http\Controllers\Admin\BuyerRiskController;
use App\Http\Controllers\Admin\BuyersController;
use App\Http\Controllers\Admin\BuyerShowController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DisputeAssignmentController;
use App\Http\Controllers\Admin\DisputeDispositionController;
use App\Http\Controllers\Admin\DisputesController;
use App\Http\Controllers\Admin\DisputeShowController;
use App\Http\Controllers\Admin\EscrowsController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\OrderShowController;
use App\Http\Controllers\Admin\ProductBulkModerationController;
use App\Http\Controllers\Admin\ProductModerationController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\ProductShowController;
use App\Http\Controllers\Admin\SellerProfilesController;
use App\Http\Controllers\Admin\SellerProfileShowController;
use App\Http\Controllers\Admin\SellerStoreStateController;
use App\Http\Controllers\Admin\SellerVerificationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserBulkManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\UserShowController;
use App\Http\Controllers\Admin\WalletLedgerExportController;
use App\Http\Controllers\Admin\WalletsController;
use App\Http\Controllers\Admin\WalletShowController;
use App\Http\Controllers\Admin\WithdrawalAssignmentController;
use App\Http\Controllers\Admin\WithdrawalReviewController;
use App\Http\Controllers\Admin\WithdrawalsController;
use App\Http\Controllers\Admin\WithdrawalShowController;
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
        Route::post('action-approvals/{approval}/decide', [AdminActionApprovalController::class, 'decide'])
            ->name('action-approvals.decide')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('action-approvals/{approval}/messages', [AdminApprovalMessageController::class, 'store'])
            ->name('action-approvals.messages.store')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::get('action-approvals/{approval}/messages', [AdminApprovalMessageController::class, 'index'])
            ->name('action-approvals.messages.index')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('action-approvals/{approval}/typing', [AdminApprovalRealtimeController::class, 'typing'])
            ->name('action-approvals.realtime.typing')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('action-approvals/{approval}/read', [AdminApprovalRealtimeController::class, 'read'])
            ->name('action-approvals.realtime.read')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::get('approvals', AdminApprovalsInboxController::class)
            ->name('approvals.index')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::get('escalations', AdminEscalationsInboxController::class)
            ->name('escalations.index')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('escalations/action', AdminEscalationIncidentActionController::class)
            ->name('escalations.action')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::get('escalation-policies', [AdminEscalationPoliciesController::class, 'index'])
            ->name('escalation-policies.index')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('escalation-policies', [AdminEscalationPoliciesController::class, 'storePolicy'])
            ->name('escalation-policies.store')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('escalation-policies/rotations', [AdminEscalationPoliciesController::class, 'storeRotation'])
            ->name('escalation-policies.rotations.store')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::get('runbooks', [AdminRunbooksController::class, 'index'])
            ->name('runbooks.index')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('runbooks', [AdminRunbooksController::class, 'store'])
            ->name('runbooks.store')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('runbooks/steps', [AdminRunbooksController::class, 'storeStep'])
            ->name('runbooks.steps.store')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::get('comms-integrations', [AdminCommsIntegrationsController::class, 'index'])
            ->name('comms-integrations.index')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('comms-integrations', [AdminCommsIntegrationsController::class, 'store'])
            ->name('comms-integrations.store')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('comms-integrations/test', [AdminCommsIntegrationsController::class, 'test'])
            ->name('comms-integrations.test')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);

        Route::get('users', UsersController::class)
            ->name('users.index')
            ->middleware('admin.permission:'.AdminPermission::USERS_VIEW);
        Route::get('buyers', BuyersController::class)
            ->name('buyers.index')
            ->middleware('admin.permission:'.AdminPermission::USERS_VIEW);
        Route::get('buyers/{buyer}', BuyerShowController::class)
            ->name('buyers.show')
            ->middleware('admin.permission:'.AdminPermission::USERS_VIEW);
        Route::post('buyers/{buyer}/risk', [BuyerRiskController::class, 'update'])
            ->name('buyers.risk-update')
            ->middleware('admin.permission:'.AdminPermission::USERS_MANAGE);
        Route::get('users/{user}', UserShowController::class)
            ->name('users.show')
            ->middleware('admin.permission:'.AdminPermission::USERS_VIEW);
        Route::post('users/{user}/state', [UserManagementController::class, 'updateState'])
            ->name('users.update-state')
            ->middleware('admin.permission:'.AdminPermission::USERS_MANAGE);
        Route::post('users/bulk-state', [UserBulkManagementController::class, 'updateState'])
            ->name('users.bulk-state')
            ->middleware('admin.permission:'.AdminPermission::USERS_MANAGE);

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
        Route::get('seller-profiles', SellerProfilesController::class)
            ->name('seller-profiles.index')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VIEW);
        Route::get('seller-profiles/{sellerProfile}', SellerProfileShowController::class)
            ->name('seller-profiles.show')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VIEW);
        Route::post('seller-profiles/{sellerProfile}/state', [SellerStoreStateController::class, 'update'])
            ->name('seller-profiles.update-state')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VERIFY);

        Route::get('products', ProductsController::class)
            ->name('products.index')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_VIEW);
        Route::get('products/{product}', ProductShowController::class)
            ->name('products.show')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_VIEW);
        Route::post('products/{product}/moderate', [ProductModerationController::class, 'updateStatus'])
            ->name('products.moderate')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE);
        Route::post('products/bulk-moderate', [ProductBulkModerationController::class, 'updateStatus'])
            ->name('products.bulk-moderate')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE);

        Route::get('orders', OrdersController::class)
            ->name('orders.index')
            ->middleware('admin.permission:'.AdminPermission::ORDERS_VIEW);

        Route::get('orders/{order}', OrderShowController::class)
            ->name('orders.show')
            ->middleware('admin.permission:'.AdminPermission::ORDERS_VIEW);

        Route::get('escrows', EscrowsController::class)
            ->name('escrows.index')
            ->middleware('admin.permission:'.AdminPermission::ESCROWS_VIEW);

        Route::get('disputes', DisputesController::class)
            ->name('disputes.index')
            ->middleware('admin.permission:'.AdminPermission::DISPUTES_VIEW);

        Route::get('disputes/{dispute}', DisputeShowController::class)
            ->name('disputes.show')
            ->middleware('admin.permission:'.AdminPermission::DISPUTES_VIEW);

        Route::post('disputes/{dispute}/move-to-review', [DisputeDispositionController::class, 'moveToReview'])
            ->name('disputes.move-to-review')
            ->middleware('admin.permission:'.AdminPermission::DISPUTES_RESOLVE);

        Route::post('disputes/{dispute}/resolve', [DisputeDispositionController::class, 'resolve'])
            ->name('disputes.resolve')
            ->middleware('admin.permission:'.AdminPermission::DISPUTES_RESOLVE);
        Route::post('disputes/{dispute}/claim', [DisputeAssignmentController::class, 'claim'])
            ->name('disputes.claim')
            ->middleware('admin.permission:'.AdminPermission::DISPUTES_RESOLVE);
        Route::post('disputes/{dispute}/unclaim', [DisputeAssignmentController::class, 'unclaim'])
            ->name('disputes.unclaim')
            ->middleware('admin.permission:'.AdminPermission::DISPUTES_RESOLVE);

        Route::get('withdrawals', WithdrawalsController::class)
            ->name('withdrawals.index')
            ->middleware('admin.permission:'.AdminPermission::WITHDRAWALS_VIEW);

        Route::get('withdrawals/{withdrawal}', WithdrawalShowController::class)
            ->name('withdrawals.show')
            ->middleware('admin.permission:'.AdminPermission::WITHDRAWALS_VIEW);

        Route::post('withdrawals/{withdrawal}/review', [WithdrawalReviewController::class, 'store'])
            ->name('withdrawals.review')
            ->middleware('admin.permission:'.AdminPermission::WITHDRAWALS_APPROVE);
        Route::post('withdrawals/{withdrawal}/claim', [WithdrawalAssignmentController::class, 'claim'])
            ->name('withdrawals.claim')
            ->middleware('admin.permission:'.AdminPermission::WITHDRAWALS_APPROVE);
        Route::post('withdrawals/{withdrawal}/unclaim', [WithdrawalAssignmentController::class, 'unclaim'])
            ->name('withdrawals.unclaim')
            ->middleware('admin.permission:'.AdminPermission::WITHDRAWALS_APPROVE);

        Route::get('wallets', WalletsController::class)
            ->name('wallets.index')
            ->middleware('admin.permission:'.AdminPermission::WALLETS_VIEW);
        Route::get('wallets/{wallet}', WalletShowController::class)
            ->name('wallets.show')
            ->middleware('admin.permission:'.AdminPermission::WALLETS_VIEW);
        Route::get('wallets/{wallet}/ledger-export', WalletLedgerExportController::class)
            ->name('wallets.ledger-export')
            ->middleware('admin.permission:'.AdminPermission::WALLETS_VIEW);

        Route::get('settings', SettingsController::class)
            ->name('settings.index')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_VIEW);

        Route::get('audit-logs', AuditLogsController::class)
            ->name('audit-logs.index')
            ->middleware('admin.permission:'.AdminPermission::AUDIT_VIEW);
        Route::get('audit-logs/export', AuditLogExportController::class)
            ->name('audit-logs.export')
            ->middleware('admin.permission:'.AdminPermission::AUDIT_VIEW);
        Route::get('audit-logs/{auditLog}', AuditLogShowController::class)
            ->name('audit-logs.show')
            ->middleware('admin.permission:'.AdminPermission::AUDIT_VIEW);
    });
});
