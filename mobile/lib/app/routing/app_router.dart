import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/application/auth_session_controller.dart';
import '../../features/auth/presentation/sign_in_gate_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/orders/presentation/order_detail_screen.dart';
import '../../features/orders/presentation/order_list_screen.dart';
import '../../features/products/presentation/product_detail_screen.dart';
import '../../features/products/presentation/product_list_screen.dart';
import '../../features/shell/presentation/app_shell_screen.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authSessionControllerProvider);

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
      ShellRoute(
        builder: (_, __, child) => AppShellScreen(child: child),
        routes: <RouteBase>[
          GoRoute(
            path: '/home',
            builder: (_, __) => const ProductListScreen(),
          ),
          GoRoute(
            path: '/orders',
            builder: (_, __) => const OrderListScreen(),
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
        ],
      ),
    ],
    redirect: (_, state) {
      final isAuth = authState.isAuthenticated;
      final location = state.matchedLocation;
      final isSplash = location == '/splash';
      final isSignIn = location == '/sign-in';

      if (authState.status == AuthStatus.unknown) {
        return isSplash ? null : '/splash';
      }

      if (!isAuth) {
        return isSignIn ? null : '/sign-in';
      }

      if (isSignIn || isSplash) {
        return '/home';
      }
      return null;
    },
  );
});
