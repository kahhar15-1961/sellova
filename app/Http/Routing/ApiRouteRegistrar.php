<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\AppServices;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DisputeController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
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
        self::categoryRoutes($routes, $app);
        self::productRoutes($routes, $app);
        self::orderRoutes($routes, $app);
        self::disputeRoutes($routes, $app);
        self::withdrawalRoutes($routes, $app);
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
}
