import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/app_providers.dart';
import '../../features/auth/application/auth_session_controller.dart';
import '../../features/auth/presentation/forgot_password_screen.dart';
import '../../features/auth/presentation/sign_in_gate_screen.dart';
import '../../features/auth/presentation/sign_up_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/categories/presentation/category_detail_screen.dart';
import '../../features/categories/presentation/category_list_screen.dart';
import '../../features/disputes/presentation/dispute_detail_screen.dart';
import '../../features/disputes/presentation/dispute_list_screen.dart';
import '../../features/disputes/presentation/create_dispute_screen.dart';
import '../../features/orders/presentation/order_detail_screen.dart';
import '../../features/orders/presentation/order_payment_screen.dart';
import '../../features/orders/presentation/order_list_screen.dart';
import '../../features/orders/presentation/rate_review_screen.dart';
import '../../features/orders/presentation/confirm_delivery_screen.dart';
import '../../features/orders/presentation/contact_seller_chat_screen.dart';
import '../../features/orders/presentation/chat_inbox_screen.dart';
import '../../features/orders/presentation/chat_thread_screen.dart';
import '../../features/orders/presentation/track_order_screen.dart';
import '../../features/orders/presentation/return_request_screen.dart';
import '../../features/orders/presentation/return_detail_screen.dart';
import '../../features/profile/presentation/admin_profile_screen.dart';
import '../../features/profile/presentation/admin_returns_queue_screen.dart';
import '../../features/profile/presentation/help_support_screen.dart';
import '../../features/profile/presentation/my_profile_screen.dart';
import '../../features/profile/presentation/my_reviews_screen.dart';
import '../../features/profile/presentation/notifications_screen.dart';
import '../../features/profile/presentation/personal_information_screen.dart';
import '../../features/profile/presentation/payment_methods_screen.dart';
import '../../features/profile/presentation/wallet_screen.dart';
import '../../features/profile/presentation/seller_profile_screen.dart';
import '../../features/profile/presentation/wishlist_screen.dart';
import '../../features/products/presentation/product_detail_screen.dart';
import '../../features/products/presentation/product_list_screen.dart';
import '../../features/storefronts/presentation/seller_storefront_screen.dart';
import '../../features/seller/presentation/seller_dashboard_screen.dart';
import '../../features/seller/presentation/seller_onboarding_screen.dart';
import '../../features/seller/presentation/seller_kyc_screen.dart';
import '../../features/seller/presentation/seller_orders_screen.dart';
import '../../features/seller/presentation/seller_order_detail_screen.dart';
import '../../features/seller/presentation/seller_update_order_status_screen.dart';
import '../../features/seller/presentation/seller_add_shipping_details_screen.dart';
import '../../features/seller/presentation/seller_order_timeline_screen.dart';
import '../../features/seller/presentation/seller_earnings_screen.dart';
import '../../features/seller/presentation/seller_menu_screen.dart';
import '../../features/seller/presentation/seller_withdraw_screen.dart';
import '../../features/seller/presentation/seller_withdraw_history_screen.dart';
import '../../features/seller/presentation/seller_reviews_screen.dart';
import '../../features/seller/presentation/seller_store_profile_screen.dart';
import '../../features/seller/presentation/seller_notifications_screen.dart';
import '../../features/seller/presentation/seller_order_chat_screen.dart';
import '../../features/seller/presentation/seller_disputes_management_screen.dart';
import '../../features/seller/presentation/seller_dispute_detail_screen.dart';
import '../../features/seller/presentation/seller_respond_dispute_screen.dart';
import '../../features/seller/presentation/seller_store_settings_screen.dart';
import '../../features/seller/presentation/seller_shipping_settings_screen.dart';
import '../../features/seller/presentation/seller_help_support_screen.dart';
import '../../features/seller/presentation/seller_bank_payment_methods_screen.dart';
import '../../features/seller/presentation/seller_returns_queue_screen.dart';
import '../../features/seller/presentation/seller_dispute_conversation_screen.dart';
import '../../features/seller/presentation/seller_dispute_resolution_screen.dart';
import '../../features/seller/presentation/seller_what_next_screen.dart';
import '../../features/seller/presentation/seller_review_detail_screen.dart';
import '../../features/seller/presentation/seller_reply_review_screen.dart';
import '../../features/seller/presentation/seller_products_screen.dart';
import '../../features/seller/presentation/seller_add_product_type_screen.dart';
import '../../features/seller/presentation/seller_add_product_screen.dart';
import '../../features/seller/presentation/seller_product_detail_screen.dart';
import '../../features/seller/presentation/seller_edit_product_screen.dart';
import '../../features/seller/presentation/seller_inventory_overview_screen.dart';
import '../../features/seller/presentation/seller_inventory_history_screen.dart';
import '../../features/seller/presentation/seller_inventory_filter_screen.dart';
import '../../features/seller/presentation/seller_inventory_movement_detail_screen.dart';
import '../../features/seller/presentation/seller_add_stock_in_screen.dart';
import '../../features/seller/presentation/seller_stock_summary_screen.dart';
import '../../features/seller/presentation/seller_add_stock_out_screen.dart';
import '../../features/seller/presentation/seller_add_adjustment_screen.dart';
import '../../features/seller/presentation/seller_warehouse_management_screen.dart';
import '../../features/shell/presentation/app_shell_screen.dart';
import '../../features/cart/presentation/cart_screen.dart';
import '../../features/cart/presentation/checkout_payment_screen.dart';
import '../../features/cart/presentation/checkout_review_screen.dart';
import '../../features/cart/presentation/checkout_shipping_screen.dart';
import '../../features/cart/presentation/add_edit_address_screen.dart';
import '../../features/cart/presentation/saved_addresses_screen.dart';
import '../../features/cart/presentation/payment_method_screens.dart';
import '../../features/cart/presentation/checkout_guard_screen.dart';
import '../../features/cart/presentation/order_success_screen.dart';
import '../../features/withdrawals/presentation/withdrawal_detail_screen.dart';
import '../../features/withdrawals/presentation/withdrawal_list_screen.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authSessionControllerProvider);
  final lastRoute =
      ref.watch(navigationStatePersistenceProvider).loadLastRoute() ?? '/home';

  return GoRouter(
    initialLocation: '/splash',
    routes: <RouteBase>[
      GoRoute(
        path: '/splash',
        builder: (_, __) => const SplashScreen(),
      ),
      GoRoute(
        path: '/sign-in',
        builder: (_, __) => const SignInGateScreen(),
      ),
      GoRoute(
        path: '/sign-up',
        builder: (_, __) => const SignUpScreen(),
      ),
      GoRoute(
        path: '/forgot-password',
        builder: (_, __) => const ForgotPasswordScreen(),
      ),
      ShellRoute(
        builder: (_, __, child) => AppShellScreen(child: child),
        routes: <RouteBase>[
          GoRoute(
            path: '/home',
            builder: (_, __) => const ProductListScreen(),
          ),
          GoRoute(
            path: '/categories',
            builder: (_, __) => const CategoryListScreen(),
          ),
          GoRoute(
            path: '/categories/:categoryId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['categoryId'] ?? '');
              if (id == null) {
                return const Scaffold(
                  body: Center(child: Text('Invalid category ID')),
                );
              }
              return CategoryDetailScreen(categoryId: id);
            },
          ),
          GoRoute(
            path: '/orders',
            builder: (_, __) => const OrderListScreen(),
          ),
          GoRoute(
            path: '/orders/:orderId/return-request',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid order ID')));
              }
              return ReturnRequestScreen(orderId: id);
            },
          ),
          GoRoute(
            path: '/returns/:returnId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['returnId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid return ID')));
              }
              return ReturnDetailScreen(returnId: id);
            },
          ),
          GoRoute(
            path: '/profile',
            builder: (_, __) => const MyProfileScreen(),
          ),
          GoRoute(
            path: '/profile/seller',
            builder: (_, __) => const SellerProfileScreen(),
          ),
          GoRoute(
            path: '/profile/admin',
            builder: (_, __) => const AdminProfileScreen(),
          ),
          GoRoute(
            path: '/profile/admin/returns',
            builder: (_, __) => const AdminReturnsQueueScreen(),
          ),
          GoRoute(
            path: '/profile/help',
            builder: (_, __) => const HelpSupportScreen(),
          ),
          GoRoute(
            path: '/profile/personal',
            builder: (_, __) => const PersonalInformationScreen(),
          ),
          GoRoute(
            path: '/profile/payment-methods',
            builder: (_, __) => const PaymentMethodsScreen(),
          ),
          GoRoute(
            path: '/profile/wallet',
            builder: (_, __) => const WalletScreen(),
          ),
          GoRoute(
            path: '/profile/reviews',
            builder: (_, __) => const MyReviewsScreen(),
          ),
          GoRoute(
            path: '/profile/notifications',
            builder: (_, __) => const NotificationsScreen(),
          ),
          GoRoute(
            path: '/profile/wishlist',
            builder: (_, __) => const WishlistScreen(),
          ),
          GoRoute(
            path: '/seller/dashboard',
            builder: (_, __) => const SellerDashboardScreen(),
          ),
          GoRoute(
            path: '/seller/analytics',
            redirect: (_, __) => '/seller/earnings',
          ),
          GoRoute(
            path: '/seller/earnings',
            builder: (_, __) => const SellerEarningsScreen(),
          ),
          GoRoute(
            path: '/seller/menu',
            builder: (_, __) => const SellerMenuScreen(),
          ),
          GoRoute(
            path: '/seller/onboarding',
            builder: (_, __) => const SellerOnboardingScreen(),
          ),
          GoRoute(
            path: '/seller/kyc',
            builder: (_, __) => const SellerKycScreen(),
          ),
          GoRoute(
            path: '/seller/withdraw',
            builder: (_, __) => const SellerWithdrawScreen(),
          ),
          GoRoute(
            path: '/seller/withdraw/history',
            builder: (_, __) => const SellerWithdrawHistoryScreen(),
          ),
          GoRoute(
            path: '/seller/reviews',
            builder: (_, __) => const SellerReviewsScreen(),
          ),
          GoRoute(
            path: '/seller/store-profile',
            builder: (_, __) => const SellerStoreProfileScreen(),
          ),
          GoRoute(
            path: '/seller/notifications',
            builder: (_, __) => const SellerNotificationsScreen(),
          ),
          GoRoute(
            path: '/seller/store-settings',
            builder: (_, __) => const SellerStoreSettingsScreen(),
          ),
          GoRoute(
            path: '/seller/shipping-settings',
            builder: (_, __) => const SellerShippingSettingsScreen(),
          ),
          GoRoute(
            path: '/seller/help-support',
            builder: (_, __) => const SellerHelpSupportScreen(),
          ),
          GoRoute(
            path: '/seller/bank-payment-methods',
            builder: (_, __) => const SellerBankPaymentMethodsScreen(),
          ),
          GoRoute(
            path: '/seller/warehouses',
            builder: (_, __) => const SellerWarehouseManagementScreen(),
          ),
          GoRoute(
            path: '/seller/returns',
            builder: (_, __) => const SellerReturnsQueueScreen(),
          ),
          GoRoute(
            path: '/seller/orders',
            builder: (_, __) => const SellerOrdersScreen(),
          ),
          GoRoute(
            path: '/seller/products',
            builder: (_, __) => const SellerProductsScreen(),
          ),
          GoRoute(
            path: '/seller/products/add/type',
            builder: (_, __) => const SellerAddProductTypeScreen(),
          ),
          GoRoute(
            path: '/seller/products/add',
            builder: (_, state) => SellerAddProductScreen(
                productType: state.uri.queryParameters['type'] ?? 'physical'),
          ),
          GoRoute(
            path: '/seller/products/:productId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['productId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid product ID')));
              }
              return SellerProductDetailScreen(productId: id);
            },
          ),
          GoRoute(
            path: '/seller/products/:productId/edit',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['productId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid product ID')));
              }
              return SellerEditProductScreen(productId: id);
            },
          ),
          GoRoute(
            path: '/seller/inventory',
            builder: (_, __) => const SellerInventoryOverviewScreen(),
          ),
          GoRoute(
            path: '/seller/inventory/history',
            builder: (_, __) => const SellerInventoryHistoryScreen(),
          ),
          GoRoute(
            path: '/seller/inventory/filter',
            builder: (_, __) => const SellerInventoryFilterScreen(),
          ),
          GoRoute(
            path: '/seller/inventory/movement/:movementId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['movementId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid movement ID')));
              }
              return SellerInventoryMovementDetailScreen(movementId: id);
            },
          ),
          GoRoute(
            path: '/seller/inventory/add-stock-in',
            builder: (_, state) {
              final id =
                  int.tryParse(state.uri.queryParameters['productId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid product ID')));
              }
              return SellerAddStockInScreen(productId: id);
            },
          ),
          GoRoute(
            path: '/seller/inventory/add-stock-out',
            builder: (_, state) {
              final id =
                  int.tryParse(state.uri.queryParameters['productId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid product ID')));
              }
              return SellerAddStockOutScreen(productId: id);
            },
          ),
          GoRoute(
            path: '/seller/inventory/add-adjustment',
            builder: (_, state) {
              final id =
                  int.tryParse(state.uri.queryParameters['productId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid product ID')));
              }
              return SellerAddAdjustmentScreen(productId: id);
            },
          ),
          GoRoute(
            path: '/seller/inventory/summary',
            builder: (_, __) => const SellerStockSummaryScreen(),
          ),
          GoRoute(
            path: '/seller/orders/:orderId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid order ID')));
              }
              return SellerOrderDetailScreen(orderId: id);
            },
          ),
          GoRoute(
            path: '/seller/orders/:orderId/chat',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid order ID')));
              }
              return SellerOrderChatScreen(orderId: id);
            },
          ),
          GoRoute(
            path: '/seller/orders/:orderId/update-status',
            pageBuilder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const MaterialPage<void>(
                    child: Scaffold(
                        body: Center(child: Text('Invalid order ID'))));
              }
              return _sellerStepTransitionPage(
                key: state.pageKey,
                child: SellerUpdateOrderStatusScreen(orderId: id),
              );
            },
          ),
          GoRoute(
            path: '/seller/orders/:orderId/shipping',
            pageBuilder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const MaterialPage<void>(
                    child: Scaffold(
                        body: Center(child: Text('Invalid order ID'))));
              }
              return _sellerStepTransitionPage(
                key: state.pageKey,
                child: SellerAddShippingDetailsScreen(orderId: id),
              );
            },
          ),
          GoRoute(
            path: '/seller/orders/:orderId/timeline',
            pageBuilder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const MaterialPage<void>(
                    child: Scaffold(
                        body: Center(child: Text('Invalid order ID'))));
              }
              return _sellerStepTransitionPage(
                key: state.pageKey,
                child: SellerOrderTimelineScreen(orderId: id),
              );
            },
          ),
          GoRoute(
            path: '/seller/disputes/chat',
            builder: (_, state) {
              final disputeId =
                  int.tryParse(state.uri.queryParameters['disputeId'] ?? '');
              final sellerView =
                  state.uri.queryParameters['sellerView'] == '1' ||
                      state.uri.queryParameters['sellerView'] == 'true';
              return SellerDisputeConversationScreen(
                sellerView: sellerView,
                disputeId: disputeId,
              );
            },
          ),
          GoRoute(
            path: '/seller/disputes/chat-seller',
            builder: (_, state) {
              final disputeId =
                  int.tryParse(state.uri.queryParameters['disputeId'] ?? '');
              return SellerDisputeConversationScreen(
                sellerView: true,
                disputeId: disputeId,
              );
            },
          ),
          GoRoute(
            path: '/seller/disputes/resolution',
            builder: (_, __) => const SellerDisputeResolutionScreen(),
          ),
          GoRoute(
            path: '/seller/disputes',
            builder: (_, __) => const SellerDisputesManagementScreen(),
          ),
          GoRoute(
            path: '/seller/disputes/:disputeId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['disputeId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid dispute ID')));
              }
              return SellerDisputeDetailScreen(disputeId: id);
            },
          ),
          GoRoute(
            path: '/seller/disputes/:disputeId/respond',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['disputeId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid dispute ID')));
              }
              return SellerRespondDisputeScreen(disputeId: id);
            },
          ),
          GoRoute(
            path: '/seller/what-next',
            builder: (_, __) => const SellerWhatNextScreen(),
          ),
          GoRoute(
            path: '/seller/reviews/:reviewId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['reviewId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid review ID')));
              }
              return SellerReviewDetailScreen(reviewId: id);
            },
          ),
          GoRoute(
            path: '/seller/reviews/:reviewId/reply',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['reviewId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid review ID')));
              }
              return SellerReplyReviewScreen(reviewId: id);
            },
          ),
          GoRoute(
            path: '/disputes',
            builder: (_, __) => const DisputeListScreen(),
          ),
          GoRoute(
            path: '/disputes/create',
            builder: (_, state) {
              final orderId =
                  int.tryParse(state.uri.queryParameters['orderId'] ?? '');
              return CreateDisputeScreen(orderId: orderId);
            },
          ),
          GoRoute(
            path: '/cart',
            builder: (_, __) => const CartScreen(),
          ),
          GoRoute(
            path: '/checkout/shipping',
            builder: (_, __) => const CheckoutShippingScreen(),
          ),
          GoRoute(
            path: '/checkout/payment',
            builder: (_, __) => const CheckoutPaymentScreen(),
          ),
          GoRoute(
            path: '/checkout/review',
            builder: (_, __) => const CheckoutReviewScreen(),
          ),
          GoRoute(
            path: '/checkout/guard',
            builder: (_, __) => const CheckoutGuardScreen(),
          ),
          GoRoute(
            path: '/addresses',
            builder: (_, __) => const SavedAddressesScreen(),
          ),
          GoRoute(
            path: '/addresses/edit',
            builder: (_, state) => AddEditAddressScreen(
                addressId: state.uri.queryParameters['addressId']),
          ),
          GoRoute(
            path: '/checkout/promo',
            redirect: (_, __) => '/checkout/review',
          ),
          GoRoute(
            path: '/checkout/payment/bkash',
            builder: (_, __) => const BkashPaymentScreen(),
          ),
          GoRoute(
            path: '/checkout/payment/nagad',
            builder: (_, __) => const NagadPaymentScreen(),
          ),
          GoRoute(
            path: '/checkout/payment/card',
            builder: (_, __) => const CardPaymentScreen(),
          ),
          GoRoute(
            path: '/order-success',
            builder: (_, GoRouterState state) {
              final orderId = state.uri.queryParameters['orderId'] ?? '0';
              final orderNumber =
                  state.uri.queryParameters['orderNumber'] ?? orderId;
              final total = state.uri.queryParameters['total'] ?? '0.00';
              final currency = (state.uri.queryParameters['currency'] ?? 'USD')
                  .toUpperCase();
              final formatted =
                  currency == 'USD' ? '\$$total' : '$currency $total';
              return OrderSuccessScreen(
                  orderId: orderId,
                  orderNumber: orderNumber,
                  totalFormatted: formatted);
            },
          ),
          GoRoute(
            path: '/withdrawals',
            builder: (_, __) => const WithdrawalListScreen(),
          ),
          GoRoute(
            path: '/withdrawals/:withdrawalId',
            builder: (_, state) {
              final id =
                  int.tryParse(state.pathParameters['withdrawalId'] ?? '');
              if (id == null) {
                return const Scaffold(
                  body: Center(child: Text('Invalid withdrawal ID')),
                );
              }
              return WithdrawalDetailScreen(withdrawalId: id);
            },
          ),
          GoRoute(
            path: '/disputes/:disputeId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['disputeId'] ?? '');
              if (id == null) {
                return const Scaffold(
                  body: Center(child: Text('Invalid dispute ID')),
                );
              }
              return DisputeDetailScreen(disputeId: id);
            },
          ),
          GoRoute(
            path: '/orders/:orderId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const Scaffold(
                  body: Center(child: Text('Invalid order ID')),
                );
              }
              return OrderDetailScreen(orderId: id);
            },
          ),
          GoRoute(
            path: '/orders/:orderId/pay',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const Scaffold(
                  body: Center(child: Text('Invalid order ID')),
                );
              }
              return OrderPaymentScreen(
                orderId: id,
                autoPay: state.uri.queryParameters['autopay'] == '1',
                paymentMethod: state.uri.queryParameters['method'] ?? 'wallet',
              );
            },
          ),
          GoRoute(
            path: '/orders/:orderId/review',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid order ID')));
              }
              return RateReviewScreen(orderId: id);
            },
          ),
          GoRoute(
            path: '/orders/:orderId/confirm-delivery',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid order ID')));
              }
              return ConfirmDeliveryScreen(orderId: id);
            },
          ),
          GoRoute(
            path: '/orders/:orderId/chat',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid order ID')));
              }
              return ContactSellerChatScreen(orderId: id);
            },
          ),
          GoRoute(
            path: '/chats',
            builder: (_, state) => ChatInboxScreen(
              panel: state.uri.queryParameters['panel'] == 'seller'
                  ? 'seller'
                  : 'buyer',
            ),
          ),
          GoRoute(
            path: '/chats/thread/:threadId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['threadId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid thread ID')));
              }
              final title = state.uri.queryParameters['title'] ?? 'Chat';
              return ChatThreadScreen(
                threadId: id,
                title: title,
                panel: state.uri.queryParameters['panel'] == 'seller'
                    ? 'seller'
                    : 'buyer',
              );
            },
          ),
          GoRoute(
            path: '/orders/:orderId/track',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['orderId'] ?? '');
              if (id == null) {
                return const Scaffold(
                    body: Center(child: Text('Invalid order ID')));
              }
              return TrackOrderScreen(orderId: id);
            },
          ),
          GoRoute(
            path: '/products/:productId',
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['productId'] ?? '');
              if (id == null) {
                return const Scaffold(
                  body: Center(child: Text('Invalid product ID')),
                );
              }
              return ProductDetailScreen(productId: id);
            },
          ),
          GoRoute(
            path: '/storefronts/:storefrontId',
            builder: (_, state) {
              final id =
                  int.tryParse(state.pathParameters['storefrontId'] ?? '');
              if (id == null) {
                return const Scaffold(
                  body: Center(child: Text('Invalid storefront ID')),
                );
              }
              return SellerStorefrontScreen(storefrontId: id);
            },
          ),
        ],
      ),
    ],
    redirect: (_, state) {
      final isAuth = authState.isAuthenticated;
      final location = state.matchedLocation;
      final isSplash = location == '/splash';
      final isSignIn = location == '/sign-in';
      final isSignUp = location == '/sign-up';
      final isForgotPassword = location == '/forgot-password';

      if (authState.status == AuthStatus.unknown) {
        return isSplash ? null : '/splash';
      }

      if (!isAuth) {
        if (isSignIn || isSignUp || isForgotPassword) {
          return null;
        }
        return '/sign-in';
      }

      if (isSignIn || isSplash || isSignUp || isForgotPassword) {
        return lastRoute;
      }
      return null;
    },
  );
});

CustomTransitionPage<void> _sellerStepTransitionPage({
  required LocalKey key,
  required Widget child,
}) {
  return CustomTransitionPage<void>(
    key: key,
    child: child,
    transitionDuration: const Duration(milliseconds: 220),
    reverseTransitionDuration: const Duration(milliseconds: 180),
    transitionsBuilder: (_, animation, secondaryAnimation, child) {
      final curved = CurvedAnimation(
          parent: animation,
          curve: Curves.easeOutCubic,
          reverseCurve: Curves.easeInCubic);
      final slide =
          Tween<Offset>(begin: const Offset(0.03, 0), end: Offset.zero)
              .animate(curved);
      final fade = Tween<double>(begin: 0.0, end: 1.0).animate(curved);
      return FadeTransition(
        opacity: fade,
        child: SlideTransition(position: slide, child: child),
      );
    },
  );
}
