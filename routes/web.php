<?php

declare(strict_types=1);

use App\Admin\AdminPermission;
use App\Http\Controllers\Admin\AdminActionApprovalController;
use App\Http\Controllers\Admin\AdminApprovalMessageController;
use App\Http\Controllers\Admin\AdminApprovalRealtimeController;
use App\Http\Controllers\Admin\AdminApprovalsInboxController;
use App\Http\Controllers\Admin\AdminCommsIntegrationsController;
use App\Http\Controllers\Admin\AdminEscalationIncidentActionController;
use App\Http\Controllers\Admin\AdminEscalationIncidentDetailController;
use App\Http\Controllers\Admin\AdminEscalationPoliciesController;
use App\Http\Controllers\Admin\AdminEscalationSloExportController;
use App\Http\Controllers\Admin\AdminEscalationsInboxController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminRunbooksController;
use App\Http\Controllers\Admin\AuditLogExportController;
use App\Http\Controllers\Admin\AuditLogsController;
use App\Http\Controllers\Admin\AuditLogShowController;
use App\Http\Controllers\Admin\AccessControlController;
use App\Http\Controllers\Admin\BuyerRiskController;
use App\Http\Controllers\Admin\BuyersController;
use App\Http\Controllers\Admin\BuyerShowController;
use App\Http\Controllers\Admin\CategoriesController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DisputeAssignmentController;
use App\Http\Controllers\Admin\DisputeDispositionController;
use App\Http\Controllers\Admin\DisputesController;
use App\Http\Controllers\Admin\DisputeShowController;
use App\Http\Controllers\Admin\EscrowActionController;
use App\Http\Controllers\Admin\EscrowsController;
use App\Http\Controllers\Admin\EscrowShowController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\OrderShowController;
use App\Http\Controllers\Admin\ProductBulkModerationController;
use App\Http\Controllers\Admin\ProductModerationController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\ProductShowController;
use App\Http\Controllers\Admin\PromotionsController;
use App\Http\Controllers\Admin\SellerProfilesController;
use App\Http\Controllers\Admin\SellerProfileShowController;
use App\Http\Controllers\Admin\SellerVerificationAssignmentController;
use App\Http\Controllers\Admin\SellerStoreStateController;
use App\Http\Controllers\Admin\SellerVerificationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShippingMethodsController;
use App\Http\Controllers\Admin\PaymentGatewaysController;
use App\Http\Controllers\Admin\UserBulkManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\UserShowController;
use App\Http\Controllers\Admin\WalletTopUpReviewController;
use App\Http\Controllers\Admin\WalletTopUpShowController;
use App\Http\Controllers\Admin\WalletTopUpsController;
use App\Http\Controllers\Admin\WalletLedgerExportController;
use App\Http\Controllers\Admin\WalletsController;
use App\Http\Controllers\Admin\WalletShowController;
use App\Http\Controllers\Admin\WithdrawalAssignmentController;
use App\Http\Controllers\Admin\WithdrawalReviewController;
use App\Http\Controllers\Admin\WithdrawalsController;
use App\Http\Controllers\Admin\WithdrawalShowController;
use App\Http\Controllers\Web\MarketplaceController;
use App\Http\Controllers\Web\MarketplaceProfileController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\WebAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketplaceController::class, 'home'])->name('web.home');
Route::get('/buyer', [MarketplaceController::class, 'buyer'])->name('web.buyer');
Route::get('/seller', [MarketplaceController::class, 'seller'])->name('web.seller');
Route::get('/login', [WebAuthController::class, 'login'])->name('login');
Route::get('/register', [WebAuthController::class, 'register'])->name('web.register');
Route::get('/forgot-password', [WebAuthController::class, 'forgotPassword'])->name('password.request');
Route::post('/login', [WebAuthController::class, 'storeLogin'])->middleware('throttle:6,1')->name('web.login.store');
Route::post('/register', [WebAuthController::class, 'storeRegister'])->middleware('throttle:6,1')->name('web.register.store');
Route::post('/forgot-password', [WebAuthController::class, 'storeForgotPassword'])->middleware('throttle:6,1')->name('password.email');
Route::post('/logout', [WebAuthController::class, 'logout'])->middleware('auth')->name('web.logout');
Route::post('/webhooks/kyc/{provider}', [MarketplaceController::class, 'kycProviderWebhook'])
    ->middleware('throttle:60,1')
    ->name('webhooks.kyc.provider');
Route::get('/marketplace', [MarketplaceController::class, 'marketplace'])->name('web.marketplace');
Route::get('/products/{productId}', [MarketplaceController::class, 'product'])->whereNumber('productId')->name('web.products.show');
Route::get('/profiles/sellers/{seller}', [MarketplaceProfileController::class, 'sellerPage'])->whereNumber('seller')->name('profiles.sellers.show');
Route::middleware('auth')->group(function (): void {
    Route::get('/profiles/buyers/{buyer}', [MarketplaceProfileController::class, 'buyerPage'])->whereNumber('buyer')->name('profiles.buyers.show');
});
Route::get('/buyer/orders/{order}', [MarketplaceController::class, 'buyerOrderShow'])->whereNumber('order')->name('buyer.orders.show');
Route::get('/seller/orders/{order}', [MarketplaceController::class, 'sellerOrderShow'])->whereNumber('order')->name('seller.orders.show');
Route::get('/{view}', [MarketplaceController::class, 'buyerView'])
    ->where('view', 'dashboard|cart|checkout|orders|order-details|escrow-orders|refund-requests|return-requests|replacement-requests|wishlist|saved-items|favorite-stores|recently-viewed|profile|profile-settings|security-settings|address-book|activity-log|wallet|top-up-history|transaction-history|referral-dashboard|loyalty-rewards|coupons-promotions|support|support-tickets|notifications|messages|product-reviews|seller-reviews|kyc-verification|device-management')
    ->name('web.buyer.view');
Route::get('/seller/withdraw/history', static fn () => redirect('/seller/withdraw-history'));
Route::get('/seller/products/create', [MarketplaceController::class, 'sellerView'])->defaults('view', 'products-create')->name('web.seller.products.create');
Route::get('/seller/products/{product}/edit', [MarketplaceController::class, 'sellerView'])->whereNumber('product')->defaults('view', 'products-edit')->name('web.seller.products.edit');
Route::get('/seller/products/{product}/preview', [MarketplaceController::class, 'sellerView'])->whereNumber('product')->defaults('view', 'products-preview')->name('web.seller.products.preview');
Route::get('/seller/{view?}', [MarketplaceController::class, 'sellerView'])
    ->where('view', 'dashboard|products|products-create|categories|inventory|stock-history|orders|order-details|payouts|wallet|top-up|top-up-history|withdraw-request|withdraw-history|transactions|delivery|offers|business|analytics|reports|earnings|support|messages|menu|kyc|reviews|notifications|store-profile|profile|store-settings|business-settings|shipping-settings|bank-payment-methods|warehouses|warehouse-form|returns|refunds|disputes')
    ->name('web.seller.view');
Route::prefix('web/actions')->name('web.actions.')->group(function (): void {
    Route::middleware(['auth', 'throttle:90,1'])->prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::get('/{notification}', [NotificationController::class, 'show'])->whereNumber('notification')->name('show');
        Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->whereNumber('notification')->name('read');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::post('/{notification}/delete', [NotificationController::class, 'destroy'])->whereNumber('notification')->name('delete');
        Route::post('/clear-all', [NotificationController::class, 'clearAll'])->name('clear-all');
    });

    Route::post('cart/add', [MarketplaceController::class, 'cartAdd'])->name('cart.add');
    Route::post('cart/update', [MarketplaceController::class, 'cartUpdate'])->name('cart.update');
    Route::post('checkout', [MarketplaceController::class, 'checkout'])->name('checkout');
    Route::post('wishlist/toggle', [MarketplaceController::class, 'wishlistToggle'])->name('wishlist.toggle');
    Route::post('buyer/payment-methods', [MarketplaceController::class, 'buyerPaymentMethodStore'])->name('buyer.payment-methods.store');
    Route::post('buyer/payment-methods/{paymentMethod}', [MarketplaceController::class, 'buyerPaymentMethodUpdate'])->whereNumber('paymentMethod')->name('buyer.payment-methods.update');
    Route::post('buyer/payment-methods/{paymentMethod}/default', [MarketplaceController::class, 'buyerPaymentMethodDefault'])->whereNumber('paymentMethod')->name('buyer.payment-methods.default');
    Route::post('buyer/payment-methods/{paymentMethod}/delete', [MarketplaceController::class, 'buyerPaymentMethodDestroy'])->whereNumber('paymentMethod')->name('buyer.payment-methods.destroy');
    Route::post('buyer/wallets/{wallet}/top-up', [MarketplaceController::class, 'buyerWalletTopUpStore'])->whereNumber('wallet')->name('buyer.wallets.top-up.store');
    Route::post('buyer/password', [MarketplaceController::class, 'profilePasswordUpdate'])->name('buyer.password.update');
    Route::post('buyer/profile-photo', [MarketplaceController::class, 'buyerProfilePhotoUpload'])->name('buyer.profile-photo.upload');
    Route::post('buyer/notification-preferences', [MarketplaceController::class, 'buyerNotificationPreferencesUpdate'])->name('buyer.notification-preferences.update');
    Route::post('buyer/activity-log/clear', [MarketplaceController::class, 'buyerActivityLogClear'])->name('buyer.activity-log.clear');
    Route::post('buyer/addresses', [MarketplaceController::class, 'buyerAddressStore'])->name('buyer.addresses.store');
    Route::post('buyer/addresses/{address}', [MarketplaceController::class, 'buyerAddressUpdate'])->whereNumber('address')->name('buyer.addresses.update');
    Route::post('buyer/addresses/{address}/delete', [MarketplaceController::class, 'buyerAddressDestroy'])->whereNumber('address')->name('buyer.addresses.destroy');
    Route::post('seller/products', [MarketplaceController::class, 'sellerProductStore'])->name('seller.products.store');
    Route::post('seller/products/{product}', [MarketplaceController::class, 'sellerProductUpdate'])->whereNumber('product')->name('seller.products.update');
    Route::post('seller/products/{product}/duplicate', [MarketplaceController::class, 'sellerProductDuplicate'])->whereNumber('product')->name('seller.products.duplicate');
    Route::post('seller/products/bulk', [MarketplaceController::class, 'sellerProductBulk'])->name('seller.products.bulk');
    Route::post('seller/media/upload', [MarketplaceController::class, 'sellerMediaUpload'])->name('seller.media.upload');
    Route::post('seller/inventory/adjust', [MarketplaceController::class, 'inventoryAdjust'])->name('seller.inventory.adjust');
    Route::post('seller/warehouses', [MarketplaceController::class, 'warehouseStore'])->name('seller.warehouses.store');
    Route::post('seller/warehouses/{sellerWarehouse}/delete', [MarketplaceController::class, 'warehouseDestroy'])->whereNumber('sellerWarehouse')->name('seller.warehouses.destroy');
    Route::post('seller/shipping-settings', [MarketplaceController::class, 'sellerShippingSettingsUpdate'])->name('seller.shipping-settings.update');
    Route::post('seller/payout-methods', [MarketplaceController::class, 'payoutMethodStore'])->name('seller.payout-methods.store');
    Route::post('seller/payout-methods/{payoutAccount}/delete', [MarketplaceController::class, 'payoutMethodDestroy'])->whereNumber('payoutAccount')->name('seller.payout-methods.destroy');
    Route::post('seller/top-ups', [MarketplaceController::class, 'topUpRequestStore'])->name('seller.top-ups.store');
    Route::post('seller/kyc/save', [MarketplaceController::class, 'sellerKycSave'])->middleware('throttle:20,1')->name('seller.kyc.save');
    Route::post('seller/kyc/submit', [MarketplaceController::class, 'sellerKycSubmit'])->middleware('throttle:6,1')->name('seller.kyc.submit');
    Route::post('seller/kyc/documents', [MarketplaceController::class, 'sellerKycDocumentUpload'])->middleware('throttle:30,1')->name('seller.kyc.documents.upload');
    Route::get('seller/kyc/documents/{document}/preview', [MarketplaceController::class, 'sellerKycDocumentPreview'])->middleware('signed')->name('seller.kyc.documents.preview');
    Route::get('support/attachments/{message}', [MarketplaceController::class, 'supportAttachmentPreview'])->whereNumber('message')->name('support.attachments.preview');
    Route::post('seller/coupons', [MarketplaceController::class, 'couponStore'])->name('seller.coupons.store');
    Route::post('seller/coupons/{promotion}', [MarketplaceController::class, 'couponUpdate'])->whereNumber('promotion')->name('seller.coupons.update');
    Route::post('seller/coupons/{promotion}/toggle', [MarketplaceController::class, 'couponToggle'])->whereNumber('promotion')->name('seller.coupons.toggle');
    Route::post('seller/coupons/{promotion}/delete', [MarketplaceController::class, 'couponDestroy'])->whereNumber('promotion')->name('seller.coupons.destroy');
    Route::post('seller/payouts', [MarketplaceController::class, 'payoutRequestStore'])->name('seller.payouts.store');
    Route::post('support/messages', [MarketplaceController::class, 'supportMessageStore'])->name('support.messages.store');
    Route::post('support/messages/read', [MarketplaceController::class, 'supportMessagesRead'])->name('support.messages.read');
    Route::get('buyer/orders/{order}', [MarketplaceController::class, 'buyerOrderApiShow'])->whereNumber('order')->name('buyer.orders.show-api');
    Route::get('seller/orders/{order}', [MarketplaceController::class, 'sellerOrderApiShow'])->whereNumber('order')->name('seller.orders.show-api');
    Route::get('orders/{order}/escrow', [MarketplaceController::class, 'orderEscrowDetail'])->whereNumber('order')->name('orders.escrow.show');
    Route::post('orders/{order}/escrow/release', [MarketplaceController::class, 'buyerOrderRelease'])->whereNumber('order')->name('orders.escrow.release');
    Route::post('orders/{order}/review', [MarketplaceController::class, 'buyerOrderReviewStore'])->whereNumber('order')->name('orders.review.store');
    Route::post('orders/{order}/buyer-review', [MarketplaceController::class, 'sellerBuyerReviewStore'])->whereNumber('order')->name('orders.buyer-review.store');
    Route::post('reviews/{review}/helpful', [MarketplaceController::class, 'reviewHelpfulStore'])->whereNumber('review')->name('reviews.helpful.store');
    Route::post('orders/{order}/escrow/dispute', [MarketplaceController::class, 'buyerOrderDisputeStore'])->whereNumber('order')->name('orders.escrow.dispute');
    Route::post('orders/{order}/escrow/delivery', [MarketplaceController::class, 'sellerOrderDeliveryStore'])->whereNumber('order')->name('orders.escrow.delivery');
    Route::post('orders/{order}/escrow/messages', [MarketplaceController::class, 'orderEscrowMessageStore'])->whereNumber('order')->name('orders.escrow.messages.store');
    Route::post('orders/{order}/escrow/messages/read', [MarketplaceController::class, 'orderEscrowMessagesRead'])->whereNumber('order')->name('orders.escrow.messages.read');
    Route::get('orders/delivery-files/{digitalDeliveryFile}/download', [MarketplaceController::class, 'deliveryFileDownload'])->middleware('signed')->whereNumber('digitalDeliveryFile')->name('orders.delivery-files.download');
    Route::get('orders/message-attachments/{orderMessageAttachment}/download', [MarketplaceController::class, 'orderMessageAttachmentDownload'])->middleware('signed')->whereNumber('orderMessageAttachment')->name('orders.messages.attachments.download');
    Route::post('profile', [MarketplaceController::class, 'profileUpdate'])->name('profile.update');
    Route::post('business', [MarketplaceController::class, 'businessUpdate'])->name('business.update');
});

Route::middleware(['auth', 'throttle:90,1'])->prefix('api')->name('marketplace.api.')->group(function (): void {
    Route::get('buyer/sellers/{seller}', [MarketplaceProfileController::class, 'sellerForBuyer'])->whereNumber('seller')->name('buyer.sellers.show');
    Route::get('seller/buyers/{buyer}', [MarketplaceProfileController::class, 'buyerForSeller'])->whereNumber('buyer')->name('seller.buyers.show');
    Route::get('admin/buyers/{buyer}', [MarketplaceProfileController::class, 'adminBuyer'])->whereNumber('buyer')->name('admin.buyers.show');
    Route::get('admin/sellers/{seller}', [MarketplaceProfileController::class, 'adminSeller'])->whereNumber('seller')->name('admin.sellers.show');
    Route::get('profiles/{type}/{id}/reviews', [MarketplaceProfileController::class, 'profileReviews'])
        ->whereIn('type', ['buyer', 'seller'])
        ->whereNumber('id')
        ->name('profiles.reviews.index');
    Route::post('reviews', [MarketplaceProfileController::class, 'storeReview'])->name('reviews.store');
    Route::post('reviews/{review}/report', [MarketplaceProfileController::class, 'reportReview'])->whereNumber('review')->name('reviews.report');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
        Route::get('forgot-password', [AdminAuthController::class, 'forgot'])->name('password.request');
        Route::post('forgot-password', [AdminAuthController::class, 'sendRecoveryCode'])
            ->middleware('throttle:admin-login')
            ->name('password.email');
        Route::get('password/otp', [AdminAuthController::class, 'otp'])->name('password.otp');
        Route::post('password/otp', [AdminAuthController::class, 'verifyOtp'])
            ->middleware('throttle:admin-login')
            ->name('password.otp.verify');
        Route::post('password/otp/resend', [AdminAuthController::class, 'resendOtp'])
            ->middleware('throttle:admin-login')
            ->name('password.otp.resend');
        Route::get('password/reset', [AdminAuthController::class, 'reset'])->name('password.reset');
        Route::post('password/reset', [AdminAuthController::class, 'updatePassword'])
            ->middleware('throttle:admin-login')
            ->name('password.update');
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
        Route::get('escalations/{incident}', [AdminEscalationIncidentDetailController::class, 'show'])
            ->name('escalations.show')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('escalations/action', AdminEscalationIncidentActionController::class)
            ->name('escalations.action')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::get('escalations/slo-export', AdminEscalationSloExportController::class)
            ->name('escalations.slo-export')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('escalations/{incident}/runbook-steps/{stepExecution}/complete', [AdminEscalationIncidentDetailController::class, 'completeRunbookStep'])
            ->name('escalations.steps.complete')
            ->middleware('admin.permission:'.AdminPermission::ACCESS);
        Route::post('escalations/{incident}/comms-deliveries/{deliveryLog}/retry', [AdminEscalationIncidentDetailController::class, 'retryCommsDelivery'])
            ->name('escalations.comms.retry')
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
        Route::get('access-control', [AccessControlController::class, 'index'])
            ->name('access-control.index')
            ;
        Route::post('access-control/roles', [AccessControlController::class, 'store'])
            ->name('access-control.roles.store')
            ;
        Route::post('access-control/roles/{role}', [AccessControlController::class, 'update'])
            ->name('access-control.roles.update')
            ;
        Route::get('promotions', [PromotionsController::class, 'index'])
            ->name('promotions.index')
            ->middleware('admin.permission:'.AdminPermission::PROMOTIONS_MANAGE.','.AdminPermission::ACCESS);
        Route::post('promotions', [PromotionsController::class, 'store'])
            ->name('promotions.store')
            ->middleware('admin.permission:'.AdminPermission::PROMOTIONS_MANAGE.','.AdminPermission::ACCESS);
        Route::patch('promotions/{promotion}', [PromotionsController::class, 'update'])
            ->name('promotions.update')
            ->middleware('admin.permission:'.AdminPermission::PROMOTIONS_MANAGE.','.AdminPermission::ACCESS);
        Route::post('promotions/{promotion}/toggle', [PromotionsController::class, 'toggle'])
            ->name('promotions.toggle')
            ->middleware('admin.permission:'.AdminPermission::PROMOTIONS_MANAGE.','.AdminPermission::ACCESS);
        Route::delete('promotions/{promotion}', [PromotionsController::class, 'destroy'])
            ->name('promotions.delete')
            ->middleware('admin.permission:'.AdminPermission::PROMOTIONS_MANAGE.','.AdminPermission::ACCESS);

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
        Route::get('sellers/export', [SellerVerificationController::class, 'export'])
            ->name('sellers.export')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VIEW);

        Route::post('sellers/kyc/{kyc}/claim', [SellerVerificationController::class, 'claim'])
            ->name('sellers.kyc.claim')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VERIFY);

        Route::post('sellers/kyc/{kyc}/review', [SellerVerificationController::class, 'review'])
            ->name('sellers.kyc.review')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VERIFY);
        Route::post('sellers/kyc/{kyc}/notes', [SellerVerificationController::class, 'storeNote'])
            ->name('sellers.kyc.note')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VERIFY);
        Route::post('sellers/kyc/{kyc}/reassign', [SellerVerificationAssignmentController::class, 'reassign'])
            ->name('sellers.kyc.reassign')
            ->middleware('admin.permission:'.AdminPermission::SELLERS_VERIFY);
        Route::post('sellers/kyc/bulk-claim', [SellerVerificationAssignmentController::class, 'bulkClaim'])
            ->name('sellers.kyc.bulk-claim')
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
        Route::post('products', [ProductsController::class, 'store'])
            ->name('products.store')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE);
        Route::post('products/bulk-discount', [ProductsController::class, 'bulkDiscount'])
            ->name('products.bulk-discount')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE);
        Route::get('products/{product}', ProductShowController::class)
            ->name('products.show')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_VIEW);
        Route::post('products/{product}/moderate', [ProductModerationController::class, 'updateStatus'])
            ->name('products.moderate')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE);
        Route::post('products/bulk-moderate', [ProductBulkModerationController::class, 'updateStatus'])
            ->name('products.bulk-moderate')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE);

        Route::get('categories', [CategoriesController::class, 'index'])
            ->name('categories.index')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE.','.AdminPermission::ACCESS);
        Route::post('categories', [CategoriesController::class, 'store'])
            ->name('categories.store')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE.','.AdminPermission::ACCESS);
        Route::patch('categories/{category}', [CategoriesController::class, 'update'])
            ->name('categories.update')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE.','.AdminPermission::ACCESS);
        Route::post('categories/{category}/toggle', [CategoriesController::class, 'toggle'])
            ->name('categories.toggle')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE.','.AdminPermission::ACCESS);
        Route::post('category-requests/{categoryRequest}/approve', [CategoriesController::class, 'approveRequest'])
            ->name('category-requests.approve')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE.','.AdminPermission::ACCESS);
        Route::post('category-requests/{categoryRequest}/reject', [CategoriesController::class, 'rejectRequest'])
            ->name('category-requests.reject')
            ->middleware('admin.permission:'.AdminPermission::PRODUCTS_MODERATE.','.AdminPermission::ACCESS);

        Route::get('orders', OrdersController::class)
            ->name('orders.index')
            ->middleware('admin.permission:'.AdminPermission::ORDERS_VIEW);

        Route::get('orders/{order}', OrderShowController::class)
            ->name('orders.show')
            ->middleware('admin.permission:'.AdminPermission::ORDERS_VIEW);
        Route::delete('orders/{order}', [OrdersController::class, 'destroy'])
            ->name('orders.destroy')
            ->middleware('admin.permission:'.AdminPermission::ORDERS_MANAGE);

        Route::get('escrows', EscrowsController::class)
            ->name('escrows.index')
            ->middleware('admin.permission:'.AdminPermission::ESCROWS_VIEW);
        Route::get('escrows/{escrow}', EscrowShowController::class)
            ->name('escrows.show')
            ->middleware('admin.permission:'.AdminPermission::ESCROWS_VIEW);
        Route::post('escrows/{escrow}/action', [EscrowActionController::class, 'store'])
            ->name('escrows.action')
            ->middleware('admin.permission:'.AdminPermission::ESCROWS_MANAGE);
        Route::delete('escrows/{escrow}', [EscrowsController::class, 'destroy'])
            ->name('escrows.destroy')
            ->middleware('admin.permission:'.AdminPermission::ESCROWS_MANAGE);

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
        Route::delete('disputes/{dispute}', [DisputesController::class, 'destroy'])
            ->name('disputes.destroy')
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
        Route::delete('wallets/{wallet}', [WalletsController::class, 'destroy'])
            ->name('wallets.destroy')
            ->middleware('admin.permission:'.AdminPermission::WALLETS_MANAGE);
        Route::get('wallet-top-ups', WalletTopUpsController::class)
            ->name('wallet-top-ups.index')
            ->middleware('admin.permission:'.AdminPermission::WALLETS_VIEW);
        Route::get('wallet-top-ups/{walletTopUpRequest}', WalletTopUpShowController::class)
            ->name('wallet-top-ups.show')
            ->middleware('admin.permission:'.AdminPermission::WALLETS_VIEW);
        Route::post('wallet-top-ups/{walletTopUpRequest}/review', [WalletTopUpReviewController::class, 'store'])
            ->name('wallet-top-ups.review')
            ->middleware('admin.permission:'.AdminPermission::WALLETS_MANAGE);

        Route::get('settings', SettingsController::class)
            ->name('settings.index')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_VIEW);
        Route::get('shipping-methods', [ShippingMethodsController::class, 'index'])
            ->name('shipping-methods.index')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE.','.AdminPermission::ACCESS);
        Route::post('shipping-methods', [ShippingMethodsController::class, 'store'])
            ->name('shipping-methods.store')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE.','.AdminPermission::ACCESS);
        Route::patch('shipping-methods/{shippingMethod}', [ShippingMethodsController::class, 'update'])
            ->name('shipping-methods.update')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE.','.AdminPermission::ACCESS);
        Route::post('shipping-methods/{shippingMethod}/toggle', [ShippingMethodsController::class, 'toggle'])
            ->name('shipping-methods.toggle')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE.','.AdminPermission::ACCESS);
        Route::post('settings/push-notifications', [SettingsController::class, 'updatePush'])
            ->name('settings.push-notifications.update')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE);
        Route::post('settings/push-notifications/test', [SettingsController::class, 'testPush'])
            ->name('settings.push-notifications.test')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE);
        Route::post('settings/escrow-timeouts', [SettingsController::class, 'updateTimeouts'])
            ->name('settings.escrow-timeouts.update')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE);
        Route::post('settings/withdrawals', [SettingsController::class, 'updateWithdrawals'])
            ->name('settings.withdrawals.update')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE);
        Route::post('notifications/read-all', [AdminNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_VIEW);
        Route::post('notifications/{notification}/read', [AdminNotificationController::class, 'markRead'])
            ->name('notifications.read')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_VIEW);
        Route::get('settings/payment-gateways', [PaymentGatewaysController::class, 'index'])
            ->name('payment-gateways.index')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_VIEW);
        Route::post('settings/payment-gateways', [PaymentGatewaysController::class, 'store'])
            ->name('payment-gateways.store')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE);
        Route::post('settings/payment-gateways/{paymentGateway}', [PaymentGatewaysController::class, 'update'])
            ->name('payment-gateways.update')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE);
        Route::post('settings/payment-gateways/{paymentGateway}/toggle', [PaymentGatewaysController::class, 'toggle'])
            ->name('payment-gateways.toggle')
            ->middleware('admin.permission:'.AdminPermission::SETTINGS_MANAGE);
        Route::post('settings/payment-gateways/{paymentGateway}/test', [PaymentGatewaysController::class, 'test'])
            ->name('payment-gateways.test')
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
