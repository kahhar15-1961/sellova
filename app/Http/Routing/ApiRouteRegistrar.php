<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\AppServices;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DisputeController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PromotionAdminController;
use App\Http\Controllers\Api\V1\PromoCodeController;
use App\Http\Controllers\Api\V1\PaymentGatewayController;
use App\Http\Controllers\Api\V1\SellerMediaController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RealtimeAuthController;
use App\Http\Controllers\Api\V1\ReturnController;
use App\Http\Controllers\Api\V1\UserProfileController;
use App\Http\Controllers\Api\V1\WithdrawalController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class ApiRouteRegistrar
{
    public static function register(RouteCollection $routes, AppServices $app): void
    {
        $routes->add('health', new Route(
            '/health',
            [
                '_controller' => static fn (): Response => new Response(
                    (string) json_encode(['status' => 'ok']),
                    Response::HTTP_OK,
                    ['Content-Type' => 'application/json'],
                ),
                '_auth' => false,
            ],
            [],
            [],
            '',
            [],
            ['GET'],
        ));

        self::authRoutes($routes, $app);
        self::profileRoutes($routes, $app);
        self::chatRoutes($routes, $app);
        self::realtimeRoutes($routes, $app);
        self::returnRoutes($routes, $app);
        self::promotionAdminRoutes($routes, $app);
        self::promoCodeRoutes($routes, $app);
        self::categoryRoutes($routes, $app);
        self::productRoutes($routes, $app);
        self::orderRoutes($routes, $app);
        self::disputeRoutes($routes, $app);
        self::withdrawalRoutes($routes, $app);
        self::paymentGatewayRoutes($routes, $app);
        self::sellerMediaRoutes($routes, $app);
    }

    private static function authRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new AuthController($app);
        $routes->add('api.v1.auth.register', new Route(
            '/api/v1/auth/register',
            ['_controller' => $c->register(...), '_auth' => false],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.auth.login', new Route(
            '/api/v1/auth/login',
            ['_controller' => $c->login(...), '_auth' => false],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.auth.refresh', new Route(
            '/api/v1/auth/refresh',
            ['_controller' => $c->refresh(...), '_auth' => false],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.auth.logout', new Route(
            '/api/v1/auth/logout',
            ['_controller' => $c->logout(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.auth.google', new Route(
            '/api/v1/auth/google',
            ['_controller' => $c->loginGoogle(...), '_auth' => false],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.auth.apple', new Route(
            '/api/v1/auth/apple',
            ['_controller' => $c->loginApple(...), '_auth' => false],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    private static function profileRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new UserProfileController($app);
        $routes->add('api.v1.me', new Route(
            '/api/v1/me',
            ['_controller' => $c->show(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.me.patch', new Route(
            '/api/v1/me',
            ['_controller' => $c->update(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.me.seller', new Route(
            '/api/v1/me/seller',
            ['_controller' => $c->showSeller(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.me.seller.patch', new Route(
            '/api/v1/me/seller',
            ['_controller' => $c->updateSeller(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.me.seller.store', new Route(
            '/api/v1/me/seller',
            ['_controller' => $c->createSeller(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.me.seller.kyc.store', new Route(
            '/api/v1/me/seller/kyc',
            ['_controller' => $c->submitSellerKyc(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.seller.shipping_settings.show', new Route(
            '/api/v1/seller/shipping/settings',
            ['_controller' => $c->showSellerShippingSettings(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.seller.shipping_settings.update', new Route(
            '/api/v1/seller/shipping/settings',
            ['_controller' => $c->updateSellerShippingSettings(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.seller.payout_methods.index', new Route(
            '/api/v1/seller/payout-methods',
            ['_controller' => $c->listSellerPayoutMethods(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.seller.payout_methods.store', new Route(
            '/api/v1/seller/payout-methods',
            ['_controller' => $c->upsertSellerPayoutMethod(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.seller.payout_methods.delete', new Route(
            '/api/v1/seller/payout-methods/{payoutMethodId}',
            ['_controller' => $c->deleteSellerPayoutMethod(...), '_auth' => true],
            ['payoutMethodId' => '\d+'],
            [],
            '',
            [],
            ['DELETE'],
        ));
        $routes->add('api.v1.seller.notifications.index', new Route(
            '/api/v1/seller/notifications',
            ['_controller' => $c->listSellerNotifications(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.seller.notifications.mark_all_read', new Route(
            '/api/v1/seller/notifications/mark-all-read',
            ['_controller' => $c->markAllSellerNotificationsRead(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.seller.reviews.index', new Route(
            '/api/v1/seller/reviews',
            ['_controller' => $c->listSellerReviews(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.me.payment_methods.index', new Route(
            '/api/v1/me/payment-methods',
            ['_controller' => $c->listPaymentMethods(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.me.payment_methods.store', new Route(
            '/api/v1/me/payment-methods',
            ['_controller' => $c->createPaymentMethod(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.me.payment_methods.update', new Route(
            '/api/v1/me/payment-methods/{paymentMethodId}/edit',
            ['_controller' => $c->updatePaymentMethod(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.me.payment_methods.default', new Route(
            '/api/v1/me/payment-methods/{paymentMethodId}',
            ['_controller' => $c->setDefaultPaymentMethod(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.me.payment_methods.delete', new Route(
            '/api/v1/me/payment-methods/{paymentMethodId}',
            ['_controller' => $c->deletePaymentMethod(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['DELETE'],
        ));
        $routes->add('api.v1.me.wishlist.index', new Route(
            '/api/v1/me/wishlist',
            ['_controller' => $c->listWishlist(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.me.wishlist.store', new Route(
            '/api/v1/me/wishlist',
            ['_controller' => $c->addWishlist(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.me.wishlist.delete', new Route(
            '/api/v1/me/wishlist/{productId}',
            ['_controller' => $c->removeWishlist(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['DELETE'],
        ));
        $routes->add('api.v1.me.reviews.index', new Route(
            '/api/v1/me/reviews',
            ['_controller' => $c->listMyReviews(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.me.reviews.store', new Route(
            '/api/v1/me/reviews',
            ['_controller' => $c->createMyReview(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.me.notifications.index', new Route(
            '/api/v1/me/notifications',
            ['_controller' => $c->listNotifications(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.me.notifications.mark_read', new Route(
            '/api/v1/me/notifications/{notificationId}/read',
            ['_controller' => $c->markNotificationRead(...), '_auth' => true],
            ['notificationId' => '\d+'],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.me.notifications.mark_all_read', new Route(
            '/api/v1/me/notifications/read-all',
            ['_controller' => $c->markAllNotificationsRead(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.me.notifications.preferences.show', new Route(
            '/api/v1/me/notifications/preferences',
            ['_controller' => $c->getNotificationPreferences(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.me.notifications.preferences.update', new Route(
            '/api/v1/me/notifications/preferences',
            ['_controller' => $c->updateNotificationPreferences(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.me.push_devices.store', new Route(
            '/api/v1/me/push-devices',
            ['_controller' => $c->registerPushDevice(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.me.push_devices.delete', new Route(
            '/api/v1/me/push-devices',
            ['_controller' => $c->unregisterPushDevice(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['DELETE'],
        ));
        $routes->add('api.v1.me.wallets.index', new Route(
            '/api/v1/me/wallets',
            ['_controller' => $c->listWallets(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.me.wallets.top_up', new Route(
            '/api/v1/me/wallets/{walletId}/top-up',
            ['_controller' => $c->topUpWallet(...), '_auth' => true],
            ['walletId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    private static function chatRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new ChatController($app);
        $routes->add('api.v1.chat.threads.index', new Route(
            '/api/v1/chat/threads',
            ['_controller' => $c->listThreads(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.chat.threads.order', new Route(
            '/api/v1/orders/{orderId}/chat-thread',
            ['_controller' => $c->getOrCreateOrderThread(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.chat.messages.index', new Route(
            '/api/v1/chat/threads/{threadId}/messages',
            ['_controller' => $c->listMessages(...), '_auth' => true],
            ['threadId' => '\d+'],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.chat.messages.store', new Route(
            '/api/v1/chat/threads/{threadId}/messages',
            ['_controller' => $c->sendMessage(...), '_auth' => true],
            ['threadId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.chat.threads.read', new Route(
            '/api/v1/chat/threads/{threadId}/read',
            ['_controller' => $c->markThreadRead(...), '_auth' => true],
            ['threadId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.chat.threads.typing', new Route(
            '/api/v1/chat/threads/{threadId}/typing',
            ['_controller' => $c->setTyping(...), '_auth' => true],
            ['threadId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.chat.threads.typing_status', new Route(
            '/api/v1/chat/threads/{threadId}/typing',
            ['_controller' => $c->typingStatus(...), '_auth' => true],
            ['threadId' => '\d+'],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.chat.support_tickets.store', new Route(
            '/api/v1/chat/support-tickets',
            ['_controller' => $c->createSupportTicket(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.chat.support_inbox.index', new Route(
            '/api/v1/chat/support-inbox',
            ['_controller' => $c->listSupportInbox(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
    }

    private static function realtimeRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new RealtimeAuthController($app);
        $routes->add('api.v1.realtime.auth', new Route(
            '/api/v1/realtime/auth',
            ['_controller' => $c->authenticate(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    private static function returnRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new ReturnController($app);
        $routes->add('api.v1.returns.index', new Route(
            '/api/v1/returns',
            ['_controller' => $c->listBuyerReturns(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.returns.store', new Route(
            '/api/v1/returns',
            ['_controller' => $c->createBuyerReturn(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.returns.eligibility', new Route(
            '/api/v1/orders/{orderId}/returns/eligibility',
            ['_controller' => $c->eligibility(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.returns.show', new Route(
            '/api/v1/returns/{returnRequestId}',
            ['_controller' => $c->show(...), '_auth' => true],
            ['returnRequestId' => '\d+'],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.seller.returns.index', new Route(
            '/api/v1/seller/returns',
            ['_controller' => $c->listSellerReturns(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.seller.returns.decide', new Route(
            '/api/v1/seller/returns/{returnRequestId}/decision',
            ['_controller' => $c->decide(...), '_auth' => true],
            ['returnRequestId' => '\d+'],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.admin.returns.queue', new Route(
            '/api/v1/admin/returns',
            ['_controller' => $c->adminQueue(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.admin.returns.escalate', new Route(
            '/api/v1/admin/returns/{returnRequestId}/escalate',
            ['_controller' => $c->escalate(...), '_auth' => true],
            ['returnRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.admin.returns.analytics', new Route(
            '/api/v1/admin/returns/analytics',
            ['_controller' => $c->analytics(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.returns.shipped_back', new Route(
            '/api/v1/returns/{returnRequestId}/shipped-back',
            ['_controller' => $c->markBuyerShipped(...), '_auth' => true],
            ['returnRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.seller.returns.received', new Route(
            '/api/v1/seller/returns/{returnRequestId}/received',
            ['_controller' => $c->markSellerReceived(...), '_auth' => true],
            ['returnRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.admin.returns.refund.submit', new Route(
            '/api/v1/admin/returns/{returnRequestId}/refund/submit',
            ['_controller' => $c->submitRefund(...), '_auth' => true],
            ['returnRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.admin.returns.refund.confirm', new Route(
            '/api/v1/admin/returns/{returnRequestId}/refund/confirm',
            ['_controller' => $c->confirmRefund(...), '_auth' => true],
            ['returnRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.admin.returns.refund.fail', new Route(
            '/api/v1/admin/returns/{returnRequestId}/refund/fail',
            ['_controller' => $c->failRefund(...), '_auth' => true],
            ['returnRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.admin.returns.auto_escalate', new Route(
            '/api/v1/admin/returns/auto-escalate',
            ['_controller' => $c->autoEscalate(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    private static function productRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new ProductController($app);
        $routes->add('api.v1.products.index', new Route(
            '/api/v1/products',
            ['_controller' => $c->index(...), '_auth' => false],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.products.store', new Route(
            '/api/v1/products',
            ['_controller' => $c->store(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.products.search', new Route(
            '/api/v1/products/search',
            ['_controller' => $c->search(...), '_auth' => false],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.products.show', new Route(
            '/api/v1/products/{productId}',
            ['_controller' => $c->show(...), '_auth' => false],
            ['productId' => '\d+'],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.products.update', new Route(
            '/api/v1/products/{productId}',
            ['_controller' => $c->update(...), '_auth' => true],
            ['productId' => '\d+'],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.products.destroy', new Route(
            '/api/v1/products/{productId}',
            ['_controller' => $c->destroy(...), '_auth' => true],
            ['productId' => '\d+'],
            [],
            '',
            [],
            ['DELETE'],
        ));
        $routes->add('api.v1.seller.products.index', new Route(
            '/api/v1/seller/products',
            ['_controller' => $c->sellerIndex(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.seller.products.store', new Route(
            '/api/v1/seller/products',
            ['_controller' => $c->sellerStore(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.seller.products.update', new Route(
            '/api/v1/seller/products/{productId}',
            ['_controller' => $c->sellerUpdate(...), '_auth' => true],
            ['productId' => '\d+'],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.seller.products.toggle', new Route(
            '/api/v1/seller/products/{productId}/toggle',
            ['_controller' => $c->sellerToggle(...), '_auth' => true],
            ['productId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    private static function categoryRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new CategoryController($app);
        $routes->add('api.v1.categories.index', new Route(
            '/api/v1/categories',
            ['_controller' => $c->index(...), '_auth' => false],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.seller.category_requests.store', new Route(
            '/api/v1/seller/category-requests',
            ['_controller' => $c->request(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    private static function promoCodeRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new PromoCodeController($app);
        $routes->add('api.v1.promo_codes.index', new Route(
            '/api/v1/promo-codes',
            ['_controller' => $c->index(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.promo_codes.validate', new Route(
            '/api/v1/promo-codes/validate',
            ['_controller' => $c->validate(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    private static function promotionAdminRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new PromotionAdminController($app);
        $routes->add('api.v1.admin.promotions.index', new Route(
            '/api/v1/admin/promotions',
            ['_controller' => $c->index(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.admin.promotions.store', new Route(
            '/api/v1/admin/promotions',
            ['_controller' => $c->store(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.admin.promotions.update', new Route(
            '/api/v1/admin/promotions/{promotionId}',
            ['_controller' => $c->update(...), '_auth' => true],
            ['promotionId' => '\d+'],
            [],
            '',
            [],
            ['PATCH'],
        ));
        $routes->add('api.v1.admin.promotions.toggle', new Route(
            '/api/v1/admin/promotions/{promotionId}/toggle',
            ['_controller' => $c->toggle(...), '_auth' => true],
            ['promotionId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.admin.promotions.delete', new Route(
            '/api/v1/admin/promotions/{promotionId}',
            ['_controller' => $c->destroy(...), '_auth' => true],
            ['promotionId' => '\d+'],
            [],
            '',
            [],
            ['DELETE'],
        ));
    }

    private static function orderRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new OrderController($app);
        $routes->add('api.v1.orders.index', new Route(
            '/api/v1/orders',
            ['_controller' => $c->index(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.seller.orders.index', new Route(
            '/api/v1/seller/orders',
            ['_controller' => $c->sellerIndex(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.seller.orders.status', new Route(
            '/api/v1/seller/orders/{orderId}/status',
            ['_controller' => $c->sellerStatus(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.seller.orders.shipping', new Route(
            '/api/v1/seller/orders/{orderId}/shipping',
            ['_controller' => $c->sellerShipping(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.seller.orders.delivery_submit', new Route(
            '/api/v1/seller/orders/{orderId}/delivery',
            ['_controller' => $c->sellerSubmitDelivery(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.orders.store', new Route(
            '/api/v1/orders',
            ['_controller' => $c->store(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.orders.show', new Route(
            '/api/v1/orders/{orderId}',
            ['_controller' => $c->show(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.orders.tracking', new Route(
            '/api/v1/orders/{orderId}/tracking',
            ['_controller' => $c->tracking(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.orders.mark_pending_payment', new Route(
            '/api/v1/orders/{orderId}/mark-pending-payment',
            ['_controller' => $c->markPendingPayment(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.orders.mark_paid', new Route(
            '/api/v1/orders/{orderId}/mark-paid',
            ['_controller' => $c->markPaid(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.orders.pay_wallet', new Route(
            '/api/v1/orders/{orderId}/pay/wallet',
            ['_controller' => $c->payWallet(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.orders.pay_manual', new Route(
            '/api/v1/orders/{orderId}/pay/manual',
            ['_controller' => $c->payManual(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.orders.advance_fulfillment', new Route(
            '/api/v1/orders/{orderId}/advance-fulfillment',
            ['_controller' => $c->advanceFulfillment(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.orders.complete', new Route(
            '/api/v1/orders/{orderId}/complete',
            ['_controller' => $c->complete(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.orders.cancel', new Route(
            '/api/v1/orders/{orderId}/cancel',
            ['_controller' => $c->cancel(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    private static function disputeRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new DisputeController($app);
        $routes->add('api.v1.disputes.index', new Route(
            '/api/v1/disputes',
            ['_controller' => $c->index(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.orders.disputes.open', new Route(
            '/api/v1/orders/{orderId}/disputes',
            ['_controller' => $c->openForOrder(...), '_auth' => true],
            ['orderId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.disputes.show', new Route(
            '/api/v1/disputes/{disputeCaseId}',
            ['_controller' => $c->show(...), '_auth' => true],
            ['disputeCaseId' => '\d+'],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.disputes.evidence', new Route(
            '/api/v1/disputes/{disputeCaseId}/evidence',
            ['_controller' => $c->submitEvidence(...), '_auth' => true],
            ['disputeCaseId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.disputes.move_to_review', new Route(
            '/api/v1/disputes/{disputeCaseId}/move-to-review',
            ['_controller' => $c->moveToReview(...), '_auth' => true],
            ['disputeCaseId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.disputes.escalate', new Route(
            '/api/v1/disputes/{disputeCaseId}/escalate',
            ['_controller' => $c->escalate(...), '_auth' => true],
            ['disputeCaseId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.disputes.resolve_refund', new Route(
            '/api/v1/disputes/{disputeCaseId}/resolve/refund',
            ['_controller' => $c->resolveRefund(...), '_auth' => true],
            ['disputeCaseId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.disputes.resolve_release', new Route(
            '/api/v1/disputes/{disputeCaseId}/resolve/release',
            ['_controller' => $c->resolveRelease(...), '_auth' => true],
            ['disputeCaseId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.disputes.resolve_split', new Route(
            '/api/v1/disputes/{disputeCaseId}/resolve/split',
            ['_controller' => $c->resolveSplit(...), '_auth' => true],
            ['disputeCaseId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    private static function withdrawalRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new WithdrawalController($app);
        $routes->add('api.v1.withdrawals.index', new Route(
            '/api/v1/withdrawals',
            ['_controller' => $c->index(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.withdrawals.settings', new Route(
            '/api/v1/withdrawals/settings',
            ['_controller' => $c->settings(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.withdrawals.store', new Route(
            '/api/v1/withdrawals',
            ['_controller' => $c->store(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.withdrawals.show', new Route(
            '/api/v1/withdrawals/{withdrawalRequestId}',
            ['_controller' => $c->show(...), '_auth' => true],
            ['withdrawalRequestId' => '\d+'],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.withdrawals.approve', new Route(
            '/api/v1/withdrawals/{withdrawalRequestId}/approve',
            ['_controller' => $c->approve(...), '_auth' => true],
            ['withdrawalRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.withdrawals.reject', new Route(
            '/api/v1/withdrawals/{withdrawalRequestId}/reject',
            ['_controller' => $c->reject(...), '_auth' => true],
            ['withdrawalRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.withdrawals.review', new Route(
            '/api/v1/withdrawals/{withdrawalRequestId}/review',
            ['_controller' => $c->review(...), '_auth' => true],
            ['withdrawalRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.withdrawals.payout.submit', new Route(
            '/api/v1/withdrawals/{withdrawalRequestId}/payout/submit',
            ['_controller' => $c->submitPayout(...), '_auth' => true],
            ['withdrawalRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.withdrawals.payout.confirm', new Route(
            '/api/v1/withdrawals/{withdrawalRequestId}/payout/confirm',
            ['_controller' => $c->confirmPayout(...), '_auth' => true],
            ['withdrawalRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
        $routes->add('api.v1.withdrawals.payout.fail', new Route(
            '/api/v1/withdrawals/{withdrawalRequestId}/payout/fail',
            ['_controller' => $c->failPayout(...), '_auth' => true],
            ['withdrawalRequestId' => '\d+'],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    private static function paymentGatewayRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new PaymentGatewayController($app);
        $routes->add('api.v1.payment_gateways.index', new Route(
            '/api/v1/payment-gateways',
            ['_controller' => $c->index(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
    }

    private static function sellerMediaRoutes(RouteCollection $routes, AppServices $app): void
    {
        $c = new SellerMediaController($app);
        $routes->add('api.v1.media.show', new Route(
            '/api/v1/media/{path}',
            ['_controller' => $c->show(...), '_auth' => false],
            ['path' => '.+'],
            [],
            '',
            [],
            ['GET'],
        ));
        $routes->add('api.v1.seller.media.upload', new Route(
            '/api/v1/seller/media/upload',
            ['_controller' => $c->upload(...), '_auth' => true],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
    }
}
